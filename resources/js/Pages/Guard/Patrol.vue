<script setup lang="ts">
import { ref, watch, onMounted } from 'vue';
import axios from 'axios';
import { useOfflineSync } from '@/Composables/useOfflineSync';
import { useGeolocation } from '@/Composables/useGeolocation';
import { CapacitorBridge } from '@/Services/CapacitorBridge';
import { Camera, CameraResultType, CameraSource } from '@capacitor/camera';

interface Checkpoint {
    id: number;
    name: string;
    description: string;
    scan_method: 'qr' | 'nfc' | 'both';
    qr_code?: string;
    nfc_tag_id?: string;
    gps_required: boolean;
    gps_fence_radius: number;
    latitude?: number;
    longitude?: number;
    photo_requirement: 'off' | 'optional' | 'required';
    note_requirement: 'off' | 'optional' | 'required';
    voice_requirement: 'off' | 'optional' | 'required';
    signature_required: boolean;
}

interface CheckpointLog {
    id: number;
    route_checkpoint_id: number;
    checkpoint_id: number;
    status: 'pending' | 'scanned' | 'skipped' | 'out_of_order_attempt';
    position: number;
    scan_method_used?: string;
    scanned_at?: string;
    note?: string;
    skip_reason?: string;
    skipped_at?: string;
    recorded_offline?: boolean;
    checkpoint: Checkpoint;
}

const props = defineProps<{
    guard: any;
    activePatrol: any;
}>();

const emit = defineEmits<{
    (e: 'patrol-completed'): void;
    (e: 'navigate', tab: string): void;
}>();

const { isOnline, addToQueue } = useOfflineSync();
const { updateLocation } = useGeolocation();

// Active states
const logs = ref<CheckpointLog[]>([]);
const currentLoadingId = ref<number | null>(null);
const skipModalCheckpoint = ref<CheckpointLog | null>(null);
const skipReason = ref('');

// Scan form states for current active checkpoint
const checkpointNote = ref('');
const checkpointPhoto = ref<string | null>(null); // base64 string
const showScanSimulator = ref(false);
const simulatorMethod = ref<'qr' | 'nfc'>('qr');
const simulatorProgress = ref(0);

// Completing patrol states
const generalNote = ref('');
const showCompletePanel = ref(false);
const completionLoading = ref(false);
const completionError = ref<string | null>(null);

// Loading & error states for checkpoint logs fetching from server
const logsLoading = ref(false);
const logsError = ref<string | null>(null);

// HTML5 Signature Canvas Refs
const sigCanvasRefs = ref<Record<number, HTMLCanvasElement>>({});
const completionSigCanvasRef = ref<HTMLCanvasElement | null>(null);
let isDrawing = false;

// HTML5 Camera File Input Refs
const photoInputRefs = ref<Record<number, HTMLInputElement>>({});

// Webcam QR scan reader instance
let html5QrCodeInstance: any = null;

// Web NFC state
const nfcStatusMessage = ref('Place the back of your phone near the NFC tag.');
const showNfcManualInput = ref(false);
const nfcManualCode = ref('');
const currentSigData = ref<string | null>(null);
let ndefAbortController: AbortController | null = null;

// Initialize signature drawing
function initSignature(canvas: HTMLCanvasElement | null) {
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;
    
    // Clear & styling
    ctx.strokeStyle = '#818cf8'; // Indigo 400
    ctx.lineWidth = 3;
    ctx.lineCap = 'round';
    
    const getPos = (e: MouseEvent | TouchEvent) => {
        const rect = canvas.getBoundingClientRect();
        const clientX = 'touches' in e ? e.touches[0].clientX : e.clientX;
        const clientY = 'touches' in e ? e.touches[0].clientY : e.clientY;
        return {
            x: clientX - rect.left,
            y: clientY - rect.top
        };
    };

    const startDraw = (e: MouseEvent | TouchEvent) => {
        isDrawing = true;
        const pos = getPos(e);
        ctx.beginPath();
        ctx.moveTo(pos.x, pos.y);
        e.preventDefault();
    };

    const draw = (e: MouseEvent | TouchEvent) => {
        if (!isDrawing) return;
        const pos = getPos(e);
        ctx.lineTo(pos.x, pos.y);
        ctx.stroke();
        e.preventDefault();
    };

    const stopDraw = () => {
        isDrawing = false;
    };

    canvas.addEventListener('mousedown', startDraw);
    canvas.addEventListener('mousemove', draw);
    window.addEventListener('mouseup', stopDraw);

    canvas.addEventListener('touchstart', startDraw, { passive: false });
    canvas.addEventListener('touchmove', draw, { passive: false });
    canvas.addEventListener('touchend', stopDraw);
}

// Clear drawing area
function clearSignature(canvas: HTMLCanvasElement | null) {
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    if (ctx) {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
    }
}

// Load logs of active patrol (fetching from server if online, otherwise cache)
async function loadPatrolLogs() {
    if (!props.activePatrol) return;
    
    logsLoading.value = true;
    logsError.value = null;
    
    // If offline, read only cache
    if (!isOnline.value) {
        const cachedLogs = localStorage.getItem(`patrol_logs_${props.activePatrol.id}`);
        if (cachedLogs) {
            logs.value = JSON.parse(cachedLogs);
        } else if (props.activePatrol.checkpoint_logs) {
            logs.value = props.activePatrol.checkpoint_logs;
            saveLogsCache();
        }
        logsLoading.value = false;
        return;
    }

    try {
        const response = await axios.get(`/api/guard/patrols/${props.activePatrol.id}`);
        if (response.data && response.data.patrol && response.data.patrol.checkpoint_logs) {
            logs.value = response.data.patrol.checkpoint_logs;
            saveLogsCache();
        } else {
            // fallback
            if (props.activePatrol.checkpoint_logs) {
                logs.value = props.activePatrol.checkpoint_logs;
            }
        }
    } catch (e: any) {
        console.error('Failed to fetch logs from server:', e);
        // Fallback to cache
        const cachedLogs = localStorage.getItem(`patrol_logs_${props.activePatrol.id}`);
        if (cachedLogs) {
            logs.value = JSON.parse(cachedLogs);
        } else {
            logsError.value = 'Failed to load checklist. Please try again.';
        }
    } finally {
        logsLoading.value = false;
    }
}

function saveLogsCache() {
    if (!props.activePatrol) return;
    localStorage.setItem(`patrol_logs_${props.activePatrol.id}`, JSON.stringify(logs.value));
}

// Watch active patrol session changes
watch(() => props.activePatrol, (newVal) => {
    if (newVal) {
        loadPatrolLogs();
        checkPatrolCompletion();
    } else {
        logs.value = [];
    }
}, { immediate: true, deep: true });

// Check if all checkpoints are done
function checkPatrolCompletion() {
    if (logs.value.length === 0) {
        showCompletePanel.value = false;
        return;
    }
    const pending = logs.value.filter(l => l.status === 'pending' || l.status === 'out_of_order_attempt');
    showCompletePanel.value = pending.length === 0;
    
    if (showCompletePanel.value) {
        setTimeout(() => {
            initSignature(completionSigCanvasRef.value);
        }, 100);
    }
}

// Helper to check if a checkpoint log is locked based on strict order enforcement
function isCheckpointLocked(log: CheckpointLog): boolean {
    if (!props.activePatrol || !props.activePatrol.route?.enforce_order) {
        return false;
    }
    // Find the first pending position
    const firstPending = logs.value.find(l => l.status === 'pending' || l.status === 'out_of_order_attempt');
    if (!firstPending) return false;
    return log.position > firstPending.position;
}

// Find current interactive pending checkpoint (fixed operator precedence bug)
const getActiveLog = () => {
    return logs.value.find(l => (l.status === 'pending' || l.status === 'out_of_order_attempt') && !isCheckpointLocked(l));
};

// Load QR scanner library dynamically from CDN
function loadQrScannerLibrary(): Promise<void> {
    return new Promise((resolve, reject) => {
        if ((window as any).Html5Qrcode) {
            resolve();
            return;
        }
        const script = document.createElement('script');
        script.src = 'https://unpkg.com/html5-qrcode';
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('Failed to load QR scanner library.'));
        document.head.appendChild(script);
    });
}

// Handle real scan trigger
async function startScan(method: 'qr' | 'nfc') {
    const activeLog = getActiveLog();
    if (!activeLog) return;

    // Photo required validation
    if (activeLog.checkpoint.photo_requirement === 'required' && !checkpointPhoto.value) {
        alert('Photo capture is required for this checkpoint.');
        return;
    }

    // Signature required validation
    let sigData = null;
    if (activeLog.checkpoint.signature_required) {
        const canvas = sigCanvasRefs.value[activeLog.id];
        if (canvas) {
            sigData = canvas.toDataURL('image/png');
            if (sigData.length < 1500) {
                alert('Digital signature is required for this checkpoint.');
                return;
            }
        }
    }

    currentSigData.value = sigData;
    simulatorMethod.value = method;

    const expectedCode = activeLog.checkpoint.qr_code || '';
    const expectedNfc = activeLog.checkpoint.nfc_tag_id || '';

    if (method === 'qr') {
        if (CapacitorBridge.isNative()) {
            try {
                const { CapacitorBarcodeScanner, CapacitorBarcodeScannerTypeHint } = await import('@capacitor/barcode-scanner');
                const result = await CapacitorBarcodeScanner.scanBarcode({
                    hint: CapacitorBarcodeScannerTypeHint.QR_CODE
                });
                if (result && result.ScanResult) {
                    const decodedText = result.ScanResult;
                    if (decodedText.trim() === expectedCode.trim()) {
                        executeScan('qr', sigData, decodedText);
                    } else {
                        alert(`Scanned code "${decodedText}" does not match the expected checkpoint QR code.`);
                    }
                }
            } catch (e: any) {
                console.error("Capacitor Barcode Scanner failed:", e);
                alert("Failed to scan QR code: " + e.message);
            }
        } else {
            showScanSimulator.value = true;
            try {
                await loadQrScannerLibrary();
                startWebcamQrScanner(sigData);
            } catch (e) {
                alert('Could not start QR scanner: ' + e);
                showScanSimulator.value = false;
            }
        }
    } else {
        showScanSimulator.value = true;
        if (CapacitorBridge.isNative()) {
            startCapacitorNfcReader(sigData);
        } else {
            startNfcTagReader(sigData);
        }
    }
}

// Start webcam QR Reader on modal mount
function startWebcamQrScanner(signatureBase64: string | null) {
    setTimeout(() => {
        const qrReaderElement = document.getElementById('qr-reader');
        if (!qrReaderElement) return;

        try {
            html5QrCodeInstance = new (window as any).Html5Qrcode("qr-reader");
            const activeLog = getActiveLog();
            const expectedCode = activeLog?.checkpoint.qr_code || '';

            html5QrCodeInstance.start(
                { facingMode: "environment" },
                {
                    fps: 10,
                    qrbox: { width: 220, height: 220 }
                },
                (decodedText: string) => {
                    if (decodedText.trim() === expectedCode.trim()) {
                        stopQrScanner();
                        showScanSimulator.value = false;
                        executeScan('qr', signatureBase64, decodedText);
                    } else {
                        alert(`Scanned code "${decodedText}" does not match the expected checkpoint QR code.`);
                    }
                },
                (errorMessage: string) => {}
            ).catch((err: any) => {
                console.error("Webcam init error:", err);
                alert("Camera permission denied, or camera is currently occupied by another app.");
                showScanSimulator.value = false;
            });
        } catch (e: any) {
            alert("QR Scanner initialization failed: " + e.message);
            showScanSimulator.value = false;
        }
    }, 400);
}

// Stop webcam QR scanner
function stopQrScanner() {
    if (html5QrCodeInstance) {
        try {
            if (html5QrCodeInstance.isScanning) {
                html5QrCodeInstance.stop().then(() => {
                    html5QrCodeInstance = null;
                }).catch((e: any) => {
                    console.error("html5QrCodeInstance stop callback error:", e);
                    html5QrCodeInstance = null;
                });
            } else {
                html5QrCodeInstance = null;
            }
        } catch (e) {
            console.error("html5QrCodeInstance stop error:", e);
            html5QrCodeInstance = null;
        }
    }
}

// Start Capacitor NFC Tag Reader
async function startCapacitorNfcReader(signatureBase64: string | null) {
    nfcStatusMessage.value = 'Place the back of your phone directly on top of the NFC tag.';
    showNfcManualInput.value = false;
    nfcManualCode.value = '';

    const activeLog = getActiveLog();
    const expectedCode = activeLog?.checkpoint.nfc_tag_id || '';

    try {
        const { CapacitorNfc } = await import('@capgo/capacitor-nfc');
        const { supported } = await CapacitorNfc.isSupported();
        if (!supported) {
            nfcStatusMessage.value = "NFC is not supported on this device.";
            showNfcManualInput.value = true;
            return;
        }

        await CapacitorNfc.startScanning({
            invalidateAfterFirstRead: true,
            alertMessage: 'Hold a tag near the device.'
        });

        const listener = await CapacitorNfc.addListener('nfcEvent', (event: any) => {
            let scannedVal = event.serialNumber || '';
            let matched = false;

            if (scannedVal && scannedVal.toLowerCase() === expectedCode.toLowerCase()) {
                matched = true;
            }

            if (event.ndefMessage && event.ndefMessage.records) {
                for (const record of event.ndefMessage.records) {
                    if (record.data) {
                        try {
                            const text = record.data;
                            if (text.trim().toLowerCase() === expectedCode.toLowerCase()) {
                                matched = true;
                                scannedVal = text;
                                break;
                            }
                        } catch (e) {
                            console.error(e);
                        }
                    }
                }
            }

            if (matched) {
                cleanupNfc();
                showScanSimulator.value = false;
                executeScan('nfc', signatureBase64, scannedVal);
            } else {
                nfcStatusMessage.value = `Scanned NFC Tag ID "${scannedVal}" does not match the expected database checkpoint value ("${expectedCode}").`;
            }
        });

        (window as any).activeNfcListener = listener;

    } catch (error: any) {
        console.error("Capacitor NFC reading failed:", error);
        nfcStatusMessage.value = "NFC Reader is restricted or busy: " + error.message;
        showNfcManualInput.value = true;
    }
}

// Start Web NFC tag reading session
async function startNfcTagReader(signatureBase64: string | null) {
    nfcStatusMessage.value = 'Place the back of your phone directly on top of the NFC tag.';
    showNfcManualInput.value = false;
    nfcManualCode.value = '';

    const activeLog = getActiveLog();
    const expectedCode = activeLog?.checkpoint.nfc_tag_id || '';

    if ('NDEFReader' in window) {
        try {
            ndefAbortController = new AbortController();
            const ndef = new (window as any).NDEFReader();
            await ndef.scan({ signal: ndefAbortController.signal });

            ndef.addEventListener("readingerror", () => {
                nfcStatusMessage.value = "Error reading NFC tag. Try repositioning the phone closer to the tag.";
            });

            ndef.addEventListener("reading", ({ message, serialNumber }: any) => {
                let matched = false;
                let scannedVal = serialNumber || '';

                if (serialNumber && serialNumber.toLowerCase() === expectedCode.toLowerCase()) {
                    matched = true;
                }

                // Parse records
                if (message && message.records) {
                    for (const record of message.records) {
                        const textDecoder = new TextDecoder(record.encoding);
                        const text = textDecoder.decode(record.data);
                        scannedVal = text;
                        if (text.trim().toLowerCase() === expectedCode.toLowerCase()) {
                            matched = true;
                            break;
                        }
                    }
                }

                if (matched) {
                    cleanupNfc();
                    showScanSimulator.value = false;
                    executeScan('nfc', signatureBase64, scannedVal);
                } else {
                    nfcStatusMessage.value = `Scanned NFC Tag ID "${scannedVal}" does not match the expected database checkpoint value ("${expectedCode}").`;
                }
            });
        } catch (error: any) {
            console.error("NFC reading failed:", error);
            nfcStatusMessage.value = "NFC Reader is restricted or busy: " + error.message;
            showNfcManualInput.value = true;
        }
    } else {
        nfcStatusMessage.value = "Web NFC (NDEFReader) is not supported on this browser/device (iOS requires native wrappers).";
        showNfcManualInput.value = true;
    }
}

async function cleanupNfc() {
    if (ndefAbortController) {
        ndefAbortController.abort();
        ndefAbortController = null;
    }
    if (CapacitorBridge.isNative()) {
        try {
            const { CapacitorNfc } = await import('@capgo/capacitor-nfc');
            if ((window as any).activeNfcListener) {
                await (window as any).activeNfcListener.remove();
                (window as any).activeNfcListener = null;
            }
            await CapacitorNfc.stopScanning();
        } catch (e) {
            console.error("Failed to stop Capacitor NFC scanning:", e);
        }
    }
}

function submitNfcManual(signatureBase64: string | null) {
    const activeLog = getActiveLog();
    const expectedCode = activeLog?.checkpoint.nfc_tag_id || '';
    if (nfcManualCode.value.trim().toLowerCase() === expectedCode.toLowerCase()) {
        cleanupNfc();
        showScanSimulator.value = false;
        executeScan('nfc', signatureBase64, nfcManualCode.value.trim());
    } else {
        alert(`Entered code "${nfcManualCode.value}" does not match the expected tag ID.`);
    }
}

// Convert base64 signature/photo to File object
function base64ToFile(base64Data: string, filename: string): File {
    const arr = base64Data.split(',');
    const mime = arr[0].match(/:(.*?);/)![1];
    const bstr = atob(arr[1]);
    let n = bstr.length;
    const u8arr = new Uint8Array(n);
    while (n--) {
        u8arr[n] = bstr.charCodeAt(n);
    }
    return new File([u8arr], filename, { type: mime });
}

// Scan processing with real code verification
async function executeScan(method: 'qr' | 'nfc', signatureBase64: string | null, scannedCodeVal?: string) {
    const log = getActiveLog();
    if (!log || !props.activePatrol) return;

    currentLoadingId.value = log.id;
    
    // Retrieve device location
    const gps = await updateLocation();
    const lat = gps ? gps.latitude : 34.671200;
    const lon = gps ? gps.longitude : 33.041200;

    const expectedCode = method === 'qr' ? (log.checkpoint.qr_code || 'DEMO-QR') : (log.checkpoint.nfc_tag_id || 'DEMO-NFC');
    const scannedCode = scannedCodeVal || expectedCode;

    const payload = {
        scan_method: method,
        latitude: lat,
        longitude: lon,
        note: checkpointNote.value || null,
        scanned_code: scannedCode
    };

    if (isOnline.value) {
        try {
            const formData = new FormData();
            formData.append('scan_method', method);
            formData.append('latitude', lat.toString());
            formData.append('longitude', lon.toString());
            formData.append('scanned_code', scannedCode);
            if (checkpointNote.value) {
                formData.append('note', checkpointNote.value);
            }
            if (checkpointPhoto.value) {
                const photoFile = base64ToFile(checkpointPhoto.value, 'checkpoint_photo.jpg');
                formData.append('media_file', photoFile);
            }
            if (signatureBase64) {
                const sigFile = base64ToFile(signatureBase64, 'signature.png');
                formData.append('signature_file', sigFile);
            }

            await axios.post(
                `/api/guard/patrols/${props.activePatrol.id}/checkpoints/${log.route_checkpoint_id}/scan`,
                formData,
                { headers: { 'Content-Type': 'multipart/form-data' } }
            );

            log.status = 'scanned';
            log.scanned_at = new Date().toISOString();
            log.scan_method_used = method;
            log.note = checkpointNote.value;
            props.activePatrol.completed_checkpoints++;
        } catch (e: any) {
            console.error('Direct scan failed, queuing offline:', e);
            queueOfflineScan(log, payload, method, signatureBase64);
        }
    } else {
        queueOfflineScan(log, payload, method, signatureBase64);
    }

    checkpointNote.value = '';
    checkpointPhoto.value = null;
    clearSignature(sigCanvasRefs.value[log.id]);
    currentLoadingId.value = null;
    saveLogsCache();
    checkPatrolCompletion();
}

function queueOfflineScan(log: CheckpointLog, payload: any, method: string, signatureBase64: string | null) {
    payload.status = 'scanned';
    payload.patrol_id = props.activePatrol.id;
    payload.route_checkpoint_id = log.route_checkpoint_id;
    addToQueue('patrol_checkpoint_log', payload);

    if (checkpointPhoto.value) {
        addToQueue('checkpoint_media', {
            patrol_checkpoint_log_id: log.id,
            base64_data: checkpointPhoto.value.split(',')[1],
            filename: 'checkpoint_photo.jpg',
            kind: 'photo',
            mime_type: 'image/jpeg',
            latitude: payload.latitude,
            longitude: payload.longitude
        });
    }

    log.status = 'scanned';
    log.scanned_at = new Date().toISOString();
    log.scan_method_used = method;
    log.note = checkpointNote.value;
    props.activePatrol.completed_checkpoints++;
}

async function executeSkip() {
    if (!skipModalCheckpoint.value || !skipReason.value.trim() || !props.activePatrol) return;

    const log = skipModalCheckpoint.value;
    currentLoadingId.value = log.id;
    
    const reason = skipReason.value;
    const payload = {
        patrol_id: props.activePatrol.id,
        route_checkpoint_id: log.route_checkpoint_id,
        status: 'skipped',
        skip_reason: reason
    };

    if (isOnline.value) {
        try {
            await axios.post(`/api/guard/patrols/${props.activePatrol.id}/checkpoints/${log.route_checkpoint_id}/skip`, {
                skip_reason: reason
            });
            log.status = 'skipped';
            log.skip_reason = reason;
            log.skipped_at = new Date().toISOString();
            props.activePatrol.skipped_checkpoints++;
        } catch (e: any) {
            console.error('Direct skip failed, queuing offline:', e);
            queueOfflineSkip(log, payload, reason);
        }
    } else {
        queueOfflineSkip(log, payload, reason);
    }

    skipModalCheckpoint.value = null;
    skipReason.value = '';
    currentLoadingId.value = null;
    saveLogsCache();
    checkPatrolCompletion();
}

function queueOfflineSkip(log: CheckpointLog, payload: any, reason: string) {
    addToQueue('patrol_checkpoint_log', payload);
    log.status = 'skipped';
    log.skip_reason = reason;
    log.skipped_at = new Date().toISOString();
    props.activePatrol.skipped_checkpoints++;
}

// Trigger photo capture, forcing native camera inside Capacitor, or using capture attribute in web
async function handleCapturePhoto(logId: number) {
    if (CapacitorBridge.isNative()) {
        try {
            const image = await Camera.getPhoto({
                quality: 85,
                allowEditing: false,
                resultType: CameraResultType.DataUrl,
                source: CameraSource.Camera // STRICTLY FORCES THE CAMERA (disables gallery picker)
            });
            if (image && image.dataUrl) {
                checkpointPhoto.value = image.dataUrl;
            }
        } catch (error: any) {
            console.error("Capacitor camera failed:", error);
            triggerWebCameraInput(logId);
        }
    } else {
        triggerWebCameraInput(logId);
    }
}

function triggerWebCameraInput(logId: number) {
    const input = photoInputRefs.value[logId];
    if (input) {
        input.setAttribute('capture', 'environment');
        input.click();
    }
}

// Handle real photo capture via camera input event
function handlePhotoCaptured(e: Event) {
    const target = e.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        const file = target.files[0];
        const reader = new FileReader();
        reader.onload = (event) => {
            if (event.target && event.target.result) {
                checkpointPhoto.value = event.target.result as string;
            }
        };
        reader.readAsDataURL(file);
    }
}

async function handleCompletePatrol() {
    if (!props.activePatrol) return;
    
    completionLoading.value = true;
    completionError.value = null;
    
    let sigData = null;
    if (completionSigCanvasRef.value) {
        sigData = completionSigCanvasRef.value.toDataURL('image/png');
    }
    
    const gps = await updateLocation();
    const lat = gps ? gps.latitude : null;
    const lon = gps ? gps.longitude : null;
    
    if (isOnline.value) {
        try {
            const formData = new FormData();
            if (generalNote.value) {
                formData.append('general_note', generalNote.value);
            }
            if (lat) {
                formData.append('completion_latitude', lat.toString());
            }
            if (lon) {
                formData.append('completion_longitude', lon.toString());
            }
            if (sigData && sigData.length > 1500) {
                const sigFile = base64ToFile(sigData, 'completion_signature.png');
                formData.append('completion_signature', sigFile);
            }
            
            await axios.post(`/api/guard/patrols/${props.activePatrol.id}/complete`, formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });
            
            localStorage.removeItem(`patrol_logs_${props.activePatrol.id}`);
            emit('patrol-completed');
        } catch (e: any) {
            console.error(e);
            completionError.value = e.response?.data?.message || 'Failed to complete patrol session online.';
            queueOfflineCompletion(sigData, lat, lon);
        }
    } else {
        queueOfflineCompletion(sigData, lat, lon);
    }
}

function queueOfflineCompletion(signatureBase64: string | null, lat: number | null, lon: number | null) {
    const payload = {
        patrol_id: props.activePatrol.id,
        general_note: generalNote.value,
        completion_latitude: lat,
        completion_longitude: lon,
        signature_base64: signatureBase64
    };
    localStorage.setItem('patrol_offline_completion_pending', JSON.stringify(payload));
    emit('patrol-completed');
}

onMounted(() => {
    loadPatrolLogs();
    checkPatrolCompletion();
    
    watch(() => getActiveLog(), (newLog) => {
        if (newLog && newLog.checkpoint.signature_required) {
            setTimeout(() => {
                initSignature(sigCanvasRefs.value[newLog.id]);
            }, 100);
        }
    }, { immediate: true });
});
</script>

<template>
  <div class="space-y-6">
    <!-- If no active patrol -->
    <div v-if="!activePatrol" class="bg-slate-900/40 border border-slate-800/80 rounded-3xl p-10 text-center py-16 animate-fadeIn">
      <div class="w-16 h-16 rounded-full bg-slate-900 border border-slate-800 flex items-center justify-center mx-auto mb-5">
        <svg class="w-8 h-8 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
        </svg>
      </div>
      <h3 class="text-base font-bold text-slate-300">No Active Patrol Shift</h3>
      <p class="text-xs text-slate-500 mt-2 max-w-xs mx-auto leading-relaxed">Please go to the Home tab, select one of your assigned patrol routes, and start shift to tracking checkpoints.</p>
      <button @click="emit('navigate', 'dashboard')" class="mt-6 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs uppercase tracking-wider px-5 py-3 rounded-xl transition-all shadow-md active:scale-95"> View Patrol Routes </button>
    </div>

    <!-- If active patrol is present -->
    <div v-else class="space-y-6 animate-fadeIn">
      <!-- Patrol Route Info Bar -->
      <div class="bg-slate-900 border border-slate-850 rounded-2xl p-4 flex justify-between items-center">
        <div>
          <h3 class="text-sm font-bold text-slate-100">{{ activePatrol.route?.name || 'Port Security Shift' }}</h3>
          <p class="text-[10px] text-slate-500 mt-0.5">Enforced Order: {{ activePatrol.route?.enforce_order ? 'Strict Sequential' : 'Flexible' }}</p>
        </div>
        <div class="text-right">
          <span class="text-[10px] font-mono font-bold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 px-2.5 py-1 rounded-lg">
            {{ activePatrol.completed_checkpoints }}/{{ activePatrol.total_checkpoints }} Checked
          </span>
        </div>
      </div>

      <!-- CHECKPOINT STEP LOG LIST -->
      <div class="space-y-3">
        <div class="flex items-center justify-between pl-1">
          <h4 class="text-xs font-black tracking-wider text-slate-400 uppercase">Route Checklist</h4>
          <button 
              v-if="!logsLoading"
              @click="loadPatrolLogs"
              class="text-[10px] text-indigo-400 hover:text-indigo-300 font-bold flex items-center gap-1 bg-indigo-500/5 px-2 py-1 rounded-lg border border-indigo-500/10 transition-all"
              title="Refresh checkpoint status from server"
          >
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            Refresh
          </button>
        </div>

        <!-- Loading logs -->
        <div v-if="logsLoading" class="flex items-center justify-center gap-3 py-10 text-slate-500">
          <div class="w-5 h-5 border-2 border-indigo-500/30 border-t-indigo-500 rounded-full animate-spin"></div>
          <span class="text-xs">Loading checkpoint data...</span>
        </div>

        <!-- Error state -->
        <div v-else-if="logsError" class="bg-rose-500/10 border border-rose-500/20 rounded-2xl px-4 py-5 text-center space-y-3">
          <p class="text-xs text-rose-400">{{ logsError }}</p>
          <button @click="loadPatrolLogs" class="text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-500 px-4 py-2 rounded-xl active:scale-95 transition-all">
            Retry
          </button>
        </div>

        <template v-else>
          <div 
            v-for="log in logs" 
            :key="log.id"
            class="bg-slate-900 border rounded-2xl p-4 transition-all duration-200"
            :class="[
              log.status === 'scanned' ? 'border-emerald-500/20 bg-emerald-950/5' : '',
              log.status === 'skipped' ? 'border-amber-500/20 bg-amber-950/5' : '',
              log.status === 'pending' && !isCheckpointLocked(log) ? 'border-indigo-500/30 ring-1 ring-indigo-500/20' : 'border-slate-850',
              isCheckpointLocked(log) ? 'opacity-40 select-none' : ''
            ]"
          >
            <div class="flex items-start justify-between">
              <div class="flex items-start space-x-3">
                <!-- Number/Status badge -->
                <div 
                  class="w-6 h-6 rounded-lg font-mono text-xs font-bold flex items-center justify-center mt-0.5 border"
                  :class="[
                    log.status === 'scanned' ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30' : '',
                    log.status === 'skipped' ? 'bg-amber-500/10 text-amber-400 border-amber-500/30' : '',
                    log.status === 'pending' && !isCheckpointLocked(log) ? 'bg-indigo-500/10 text-indigo-400 border-indigo-500/30' : 'bg-slate-950 text-slate-600 border-slate-800'
                  ]"
                >
                  <svg v-if="log.status === 'scanned'" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                  </svg>
                  <svg v-else-if="log.status === 'skipped'" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                  </svg>
                  <span v-else>{{ log.position }}</span>
                </div>

                <div>
                  <h5 class="text-xs font-bold" :class="log.status === 'scanned' ? 'text-slate-300' : 'text-slate-100'">
                    {{ log.checkpoint.name }}
                  </h5>
                  <p class="text-[11px] text-slate-500 mt-1 leading-relaxed">{{ log.checkpoint.description }}</p>
                </div>
              </div>

              <!-- Lock or Skip action -->
              <div class="flex items-center space-x-2">
                <span v-if="isCheckpointLocked(log)" class="text-slate-600" title="Locked by order sequence">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                  </svg>
                </span>
                <!-- Skip Button -->
                <button 
                  v-else-if="log.status === 'pending' && activePatrol.route?.allow_skip" 
                  @click="skipModalCheckpoint = log"
                  class="text-[10px] text-amber-500 hover:text-amber-400 font-bold uppercase tracking-wider bg-amber-500/5 px-2.5 py-1 rounded-lg border border-amber-500/10 active:scale-95"
                >
                  Skip
                </button>
              </div>
            </div>

            <!-- EXPANDED SCAN UTILITY (For Current Active Checkpoint) -->
            <div 
              v-if="log.status === 'pending' && !isCheckpointLocked(log)"
              class="mt-4 pt-4 border-t border-slate-800/80 space-y-4 animate-fadeIn"
            >
              <!-- Checkpoint Requirements tags -->
              <div class="flex flex-wrap gap-1.5 text-[9px] font-bold text-slate-400">
                <span class="bg-slate-950 px-2 py-0.5 rounded border border-slate-800 flex items-center">
                  METHOD: {{ log.checkpoint.scan_method.toUpperCase() }}
                </span>
                <span 
                  v-if="log.checkpoint.photo_requirement !== 'off'"
                  class="px-2 py-0.5 rounded border flex items-center"
                  :class="log.checkpoint.photo_requirement === 'required' ? 'bg-indigo-500/10 border-indigo-500/20 text-indigo-400' : 'bg-slate-950 border-slate-800 text-slate-400'"
                >
                  PHOTO: {{ log.checkpoint.photo_requirement.toUpperCase() }}
                </span>
                <span 
                  v-if="log.checkpoint.signature_required"
                  class="bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 px-2 py-0.5 rounded flex items-center"
                >
                  SIGNATURE REQUIRED
                </span>
              </div>

              <!-- Optional Note Input -->
              <div>
                <textarea 
                  v-model="checkpointNote"
                  rows="2"
                  class="w-full bg-slate-950 border border-slate-850 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 text-slate-100 p-2.5 rounded-xl text-xs focus:outline-none"
                  placeholder="Add notes (optional)..."
                ></textarea>
              </div>

              <!-- Real Photo Capture Camera Hook -->
              <div v-if="log.checkpoint.photo_requirement !== 'off'" class="space-y-2">
                <div class="flex items-center space-x-3">
                  <input 
                    type="file" 
                    :ref="el => { if (el) photoInputRefs[log.id] = el as any }" 
                    accept="image/*" 
                    capture="environment" 
                    class="hidden" 
                    @change="handlePhotoCaptured" 
                  />
                  <button 
                    @click="handleCapturePhoto(log.id)" 
                    class="py-2.5 px-4 bg-slate-850 hover:bg-slate-800 text-slate-200 border border-slate-800 text-xs font-bold rounded-xl active:scale-95 transition-all flex items-center space-x-2"
                  >
                    <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span>Capture Checkpoint Photo</span>
                  </button>
                  <span v-if="checkpointPhoto" class="text-[10px] text-emerald-400 font-bold flex items-center space-x-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>Photo Captured</span>
                  </span>
                </div>
                <div v-if="checkpointPhoto" class="w-24 aspect-video border border-slate-800 rounded-lg overflow-hidden bg-slate-950">
                  <img :src="checkpointPhoto" class="w-full h-full object-cover" />
                </div>
              </div>

              <!-- Dynamic Signature Canvas Box -->
              <div v-if="log.checkpoint.signature_required" class="space-y-2">
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Digital Sign-off</label>
                <div class="relative bg-slate-950 border border-slate-850 rounded-xl overflow-hidden">
                  <canvas 
                    :ref="el => { if (el) sigCanvasRefs[log.id] = el as any }" 
                    width="320" 
                    height="120"
                    class="w-full h-[120px] bg-slate-950 cursor-crosshair touch-none"
                  ></canvas>
                  <button 
                    @click="clearSignature(sigCanvasRefs[log.id])"
                    class="absolute right-2 bottom-2 bg-slate-900/80 backdrop-blur border border-slate-800 text-[10px] uppercase tracking-wide font-bold px-2 py-1 rounded text-slate-400"
                  >
                    Clear
                  </button>
                </div>
              </div>

              <!-- Interactive Scan Buttons -->
              <div class="grid grid-cols-2 gap-3">
                <button 
                  v-if="log.checkpoint.scan_method === 'qr' || log.checkpoint.scan_method === 'both'"
                  @click="startScan('qr')"
                  :disabled="currentLoadingId === log.id"
                  class="py-3.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-xl text-xs uppercase tracking-wider shadow-md active:scale-95 transition-all flex items-center justify-center space-x-2"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                  </svg>
                  <span>Scan QR Code</span>
                </button>

                <button 
                  v-if="log.checkpoint.scan_method === 'nfc' || log.checkpoint.scan_method === 'both'"
                  @click="startScan('nfc')"
                  :disabled="currentLoadingId === log.id"
                  class="py-3.5 bg-violet-650 hover:bg-violet-600 text-white font-bold rounded-xl text-xs uppercase tracking-wider shadow-md active:scale-95 transition-all flex items-center justify-center space-x-2"
                  :class="log.checkpoint.scan_method === 'nfc' ? 'col-span-2' : ''"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                  </svg>
                  <span>Scan NFC Tag</span>
                </button>
              </div>
            </div>
          </div>
        </template>
      </div>

      <!-- COMPLETE PATROL REPORT SCREEN (Visible when checklist complete) -->
      <div 
        v-if="showCompletePanel" 
        class="bg-slate-900 border border-emerald-500/20 bg-gradient-to-br from-slate-900 via-slate-900 to-emerald-950/20 rounded-3xl p-5 shadow-xl space-y-4 animate-fadeIn"
      >
        <div class="flex items-center space-x-2 text-emerald-400">
          <svg class="w-5 h-5 animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <h4 class="text-sm font-bold uppercase tracking-wider">Patrol Route Completed</h4>
        </div>
        <p class="text-xs text-slate-400 leading-relaxed">All route checkpoints have been checked or skipped. Please sign off and submit the patrol report below.</p>

        <!-- General Shift Note -->
        <div>
          <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">General Patrol Notes</label>
          <textarea 
            v-model="generalNote"
            rows="3"
            class="w-full bg-slate-950 border border-slate-850 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 text-slate-100 p-2.5 rounded-xl text-xs focus:outline-none"
            placeholder="Provide details about gates, security observations, shift occurrences..."
          ></textarea>
        </div>

        <!-- Complete Sign-off signature pad -->
        <div class="space-y-2">
          <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Guard Final Signature</label>
          <div class="relative bg-slate-950 border border-slate-850 rounded-xl overflow-hidden">
            <canvas 
              ref="completionSigCanvasRef" 
              width="320" 
              height="130"
              class="w-full h-[130px] bg-slate-950 cursor-crosshair touch-none"
            ></canvas>
            <button 
              @click="clearSignature(completionSigCanvasRef)"
              class="absolute right-2 bottom-2 bg-slate-900/80 backdrop-blur border border-slate-800 text-[10px] uppercase tracking-wide font-bold px-2 py-1 rounded text-slate-400"
            >
              Clear
            </button>
          </div>
        </div>

        <div v-if="completionError" class="text-xs text-rose-400 text-center font-medium bg-rose-500/10 p-2.5 rounded-xl">
          {{ completionError }}
        </div>

        <button 
          @click="handleCompletePatrol"
          :disabled="completionLoading"
          class="w-full py-4 bg-gradient-to-r from-emerald-600 to-teal-700 hover:from-emerald-700 hover:to-teal-850 text-white font-bold rounded-2xl shadow-xl shadow-emerald-600/20 transition-all flex items-center justify-center space-x-2 text-xs uppercase tracking-widest active:scale-98"
        >
          <span v-if="completionLoading" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
          <span v-else>SUBMIT PATROL REPORT</span>
        </button>
      </div>
    </div>

    <!-- SKIP REASON MODAL -->
    <div 
      v-if="skipModalCheckpoint" 
      class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-6"
    >
      <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 w-full max-w-sm space-y-4 shadow-2xl animate-fadeIn">
        <h4 class="text-sm font-bold text-slate-100">Skip Checkpoint: {{ skipModalCheckpoint.checkpoint.name }}</h4>
        <p class="text-xs text-slate-400">Please provide a valid security reason for skipping this checkpoint. This skip log will be recorded in the audit history.</p>
        
        <textarea 
          v-model="skipReason"
          rows="3"
          class="w-full bg-slate-950 border border-slate-850 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 text-slate-100 p-2.5 rounded-xl text-xs focus:outline-none"
          placeholder="E.g. Access blocked by cargo, gate keys missing, area locked by facility manager..."
        ></textarea>

        <div class="flex space-x-3">
          <button 
            @click="skipModalCheckpoint = null; skipReason = ''"
            class="flex-1 py-3 bg-slate-800 hover:bg-slate-750 text-slate-300 text-xs font-bold uppercase tracking-wider rounded-xl active:scale-95"
          >
            Cancel
          </button>
          <button 
            @click="executeSkip"
            :disabled="!skipReason.trim()"
            class="flex-1 py-3 bg-amber-600 hover:bg-amber-500 disabled:opacity-40 disabled:pointer-events-none text-white text-xs font-bold uppercase tracking-wider rounded-xl active:scale-95 shadow-lg shadow-amber-600/20"
          >
            Confirm Skip
          </button>
        </div>
      </div>
    </div>

    <!-- SCAN OVERLAY / SCANNER SCREEN -->
    <div 
      v-if="showScanSimulator" 
      class="fixed inset-0 z-50 bg-slate-950/95 flex flex-col justify-center items-center p-6"
    >
      <div v-if="simulatorMethod === 'qr'" class="w-full max-w-sm flex flex-col items-center space-y-6">
        <h4 class="text-sm font-bold uppercase tracking-widest text-indigo-400"> Scan Checkpoint QR Code </h4>
        <p class="text-xs text-slate-400 text-center">Place the QR code inside the frame to scan automatically.</p>
        
        <!-- Webcam reader target -->
        <div id="qr-reader" class="w-64 h-64 rounded-3xl overflow-hidden border border-indigo-500/30 bg-black relative">
          <!-- Frame border effect -->
          <div class="absolute inset-4 border border-dashed border-indigo-500/40 rounded-2xl pointer-events-none z-10"></div>
        </div>

        <button 
          @click="stopQrScanner(); showScanSimulator = false"
          class="px-6 py-3 bg-slate-900 hover:bg-slate-850 text-slate-300 font-bold text-xs uppercase tracking-wider rounded-xl border border-slate-800 active:scale-95"
        >
          Cancel Scan
        </button>
      </div>

      <div v-else class="w-full max-w-sm flex flex-col items-center space-y-6 text-center">
        <!-- NFC icon/pulse -->
        <div class="w-24 h-24 rounded-full bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center animate-pulse mb-4">
          <svg class="w-12 h-12 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
          </svg>
        </div>
        
        <h4 class="text-sm font-bold uppercase tracking-widest text-indigo-400"> NFC Tag Reader </h4>
        <p class="text-xs text-slate-300 px-4 leading-relaxed">{{ nfcStatusMessage }}</p>

        <!-- Manual fallback input if Web NFC not supported -->
        <div v-if="showNfcManualInput" class="w-full space-y-3 px-4 pt-4 border-t border-slate-900">
          <p class="text-[10px] text-slate-500 uppercase tracking-wider font-bold">Manual Fallback Code Entry</p>
          <input 
            v-model="nfcManualCode"
            type="text"
            class="w-full bg-slate-900 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 text-slate-100 p-3 rounded-xl text-xs focus:outline-none text-center font-mono uppercase tracking-widest"
            placeholder="Type NFC code..."
          />
          <button 
            @click="submitNfcManual(currentSigData)"
            :disabled="!nfcManualCode.trim()"
            class="w-full py-3 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-40 disabled:pointer-events-none text-white font-bold text-xs uppercase tracking-wider rounded-xl active:scale-95 transition-all"
          >
            Submit Tag ID
          </button>
        </div>

        <button 
          @click="cleanupNfc(); showScanSimulator = false"
          class="px-6 py-3 bg-slate-900 hover:bg-slate-850 text-slate-300 font-bold text-xs uppercase tracking-wider rounded-xl border border-slate-800 active:scale-95"
        >
          Close Reader
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.animate-scan {
  animation: scan 2s linear infinite;
}
@keyframes scan {
  0% { top: 0%; }
  50% { top: 100%; }
  100% { top: 0%; }
}
</style>