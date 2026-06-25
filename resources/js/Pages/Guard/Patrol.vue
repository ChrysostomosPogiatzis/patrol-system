<script setup lang="ts">
import { useGeolocation } from '@/Composables/useGeolocation';
import { useOfflineSync } from '@/Composables/useOfflineSync';
import { CapacitorBridge } from '@/Services/CapacitorBridge';
import { Camera, CameraResultType, CameraSource } from '@capacitor/camera';
import axios from 'axios';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

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
    (e: 'checkpoint-scanned'): void;
    (e: 'checkpoint-skipped'): void;
}>();

const { isOnline, addToQueue, queue, isSyncing, triggerSync } =
    useOfflineSync();
const { updateLocation, currentPosition } = useGeolocation();

// Active states
const logs = ref<CheckpointLog[]>([]);
const routeDetails = ref<any>(props.activePatrol?.route || null);
const currentLoadingId = ref<number | null>(null);
const skipModalCheckpoint = ref<CheckpointLog | null>(null);
const skipReason = ref('');

// Scan form states for current active checkpoint
const checkpointNote = ref('');
const checkpointPhoto = ref<string | null>(null); // base64 string

// Voice memo recording states for checkpoints
const isVoiceRecording = ref(false);
const voiceRecordingDuration = ref(0);
const checkpointVoiceBlob = ref<Blob | null>(null);
const checkpointVoiceUrl = ref<string | null>(null);
let checkpointMediaRecorder: MediaRecorder | null = null;
let checkpointRecordingTimer: any = null;

async function startVoiceRecording() {
    try {
        const stream = await navigator.mediaDevices.getUserMedia({
            audio: true,
        });
        checkpointMediaRecorder = new MediaRecorder(stream);
        const chunks: Blob[] = [];

        checkpointMediaRecorder.ondataavailable = (e) => {
            if (e.data.size > 0) {
                chunks.push(e.data);
            }
        };

        checkpointMediaRecorder.onstop = () => {
            checkpointVoiceBlob.value = new Blob(chunks, { type: 'audio/wav' });
            checkpointVoiceUrl.value = URL.createObjectURL(
                checkpointVoiceBlob.value,
            );
            stream.getTracks().forEach((track) => track.stop());
        };

        isVoiceRecording.value = true;
        voiceRecordingDuration.value = 0;
        checkpointMediaRecorder.start();

        checkpointRecordingTimer = setInterval(() => {
            voiceRecordingDuration.value++;
        }, 1000);
    } catch (err) {
        console.error('Microphone access failed:', err);
        alert('Could not access microphone.');
    }
}

function stopVoiceRecording() {
    if (
        checkpointMediaRecorder &&
        checkpointMediaRecorder.state !== 'inactive'
    ) {
        checkpointMediaRecorder.stop();
    }
    isVoiceRecording.value = false;
    if (checkpointRecordingTimer) {
        clearInterval(checkpointRecordingTimer);
        checkpointRecordingTimer = null;
    }
}

function deleteVoiceRecording() {
    checkpointVoiceBlob.value = null;
    checkpointVoiceUrl.value = null;
    voiceRecordingDuration.value = 0;
}

function blobToBase64(blob: Blob): Promise<string> {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onloadend = () => {
            const base64String = reader.result as string;
            resolve(base64String.split(',')[1]);
        };
        reader.onerror = reject;
        reader.readAsDataURL(blob);
    });
}
const showScanSimulator = ref(false);
const simulatorMethod = ref<'qr' | 'nfc'>('qr');
const simulatorProgress = ref(0);

// Map states
const patrolMapContainer = ref<HTMLDivElement | null>(null);
const isMapLoaded = ref(false);
const mapLoadError = ref<string | null>(null);
let mapInstance: any = null;
let markersLayerGroup: any = null;
let polylineLayerGroup: any = null;

function loadLeaflet(): Promise<void> {
    return new Promise((resolve, reject) => {
        if ((window as any).L) {
            resolve();
            return;
        }

        // CSS
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
        document.head.appendChild(link);

        // JS
        const script = document.createElement('script');
        script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
        script.onload = () => resolve();
        script.onerror = () =>
            reject(new Error('Leaflet library failed to load.'));
        document.head.appendChild(script);
    });
}

function initMap() {
    if (!patrolMapContainer.value) return;

    const L = (window as any).L;
    if (!L) return;

    // Center coordinates - default to first checkpoint if available, or Limassol fallback
    let centerLat = 34.6786;
    let centerLon = 33.0413;
    let zoomLevel = 15;

    // Find first checkpoint with valid coordinates
    const firstValidCp = logs.value.find(
        (log) =>
            log.checkpoint &&
            log.checkpoint.latitude &&
            log.checkpoint.longitude,
    );
    if (firstValidCp?.checkpoint) {
        centerLat = Number(firstValidCp.checkpoint.latitude);
        centerLon = Number(firstValidCp.checkpoint.longitude);
    } else if (currentPosition.value) {
        centerLat = currentPosition.value.latitude;
        centerLon = currentPosition.value.longitude;
    }

    mapInstance = L.map(patrolMapContainer.value, {
        center: [centerLat, centerLon],
        zoom: zoomLevel,
        zoomControl: false, // compact for mobile
    });

    // Dark-themed tiles
    L.tileLayer(
        'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
        {
            attribution: '&copy; OpenStreetMap &copy; CARTO',
            subdomains: 'abcd',
            maxZoom: 20,
        },
    ).addTo(mapInstance);

    markersLayerGroup = L.layerGroup().addTo(mapInstance);
    polylineLayerGroup = L.layerGroup().addTo(mapInstance);

    renderMapLayers();
}

function renderMapLayers() {
    const L = (window as any).L;
    if (!L || !mapInstance || !markersLayerGroup || !polylineLayerGroup) return;

    // Clear previous elements
    markersLayerGroup.clearLayers();
    polylineLayerGroup.clearLayers();

    // 1. Draw Checkpoints
    const routeCoords: [number, number][] = [];

    // Sort logs by position to ensure sequential path lines
    const sortedLogs = [...logs.value].sort((a, b) => a.position - b.position);

    sortedLogs.forEach((log) => {
        const cp = log.checkpoint;
        if (
            !cp ||
            cp.latitude === undefined ||
            cp.longitude === undefined ||
            cp.latitude === null ||
            cp.longitude === null
        )
            return;

        const lat = Number(cp.latitude);
        const lon = Number(cp.longitude);
        routeCoords.push([lat, lon]);

        // Color coding
        // scanned = green (#10B981), skipped = orange (#F59E0B), pending = indigo (#6366F1)
        let color = '#6366F1';
        let statusText = 'Pending';
        if (log.status === 'scanned') {
            color = '#10B981';
            statusText = 'Scanned';
        } else if (log.status === 'skipped') {
            color = '#F59E0B';
            statusText = 'Skipped';
        }

        // Draw checkpoint circle
        const circle = L.circleMarker([lat, lon], {
            radius: 8,
            fillColor: color,
            color: '#FFFFFF',
            weight: 2,
            opacity: 1,
            fillOpacity: 0.9,
        });

        circle.bindPopup(`
            <div class="text-xs font-sans text-slate-900">
                <p class="font-bold">${cp.name}</p>
                <p class="text-[10px] text-slate-500">${statusText}</p>
            </div>
        `);

        markersLayerGroup.addLayer(circle);
    });

    // 2. Draw Polyline Path (dashed)
    if (routeCoords.length > 1) {
        const polyline = L.polyline(routeCoords, {
            color: '#6366F1',
            weight: 2,
            dashArray: '5, 8',
            opacity: 0.6,
        });
        polylineLayerGroup.addLayer(polyline);
    }

    // 3. Draw Guard Location
    if (currentPosition.value) {
        const lat = currentPosition.value.latitude;
        const lon = currentPosition.value.longitude;

        // Custom pulsing marker or simple circle marker with pulsing CSS
        const guardCircle = L.circleMarker([lat, lon], {
            radius: 6,
            fillColor: '#3B82F6',
            color: '#FFFFFF',
            weight: 2,
            opacity: 1,
            fillOpacity: 1,
        });

        // Add a secondary larger, transparent circle for pulsing look
        const pulseCircle = L.circle([lat, lon], {
            radius: 15,
            fillColor: '#3B82F6',
            color: '#3B82F6',
            weight: 1,
            opacity: 0.4,
            fillOpacity: 0.15,
        });

        guardCircle.bindPopup(
            '<div class="text-xs font-sans text-slate-900 font-bold">Your Location</div>',
        );

        markersLayerGroup.addLayer(pulseCircle);
        markersLayerGroup.addLayer(guardCircle);
    }
}

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
            y: clientY - rect.top,
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
function isCanvasBlank(canvas: HTMLCanvasElement): boolean {
    const context = canvas.getContext('2d');
    if (!context) return true;
    const buffer = new Uint32Array(
        context.getImageData(0, 0, canvas.width, canvas.height).data.buffer,
    );
    return !buffer.some((color) => color !== 0);
}

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
        const cachedLogs = localStorage.getItem(
            `patrol_logs_${props.activePatrol.id}`,
        );
        if (cachedLogs) {
            logs.value = JSON.parse(cachedLogs);
        } else if (props.activePatrol.checkpoint_logs) {
            logs.value = props.activePatrol.checkpoint_logs;
            saveLogsCache();
        }
        logsLoading.value = false;
        checkPatrolCompletion();
        return;
    }

    try {
        const response = await axios.get(
            `/api/guard/patrols/${props.activePatrol.id}`,
            { timeout: 5000 },
        );
        if (response.data && response.data.patrol) {
            if (response.data.patrol.checkpoint_logs) {
                logs.value = response.data.patrol.checkpoint_logs;
            }
            if (response.data.patrol.route) {
                routeDetails.value = response.data.patrol.route;
            }
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
        const cachedLogs = localStorage.getItem(
            `patrol_logs_${props.activePatrol.id}`,
        );
        if (cachedLogs) {
            logs.value = JSON.parse(cachedLogs);
        } else {
            logsError.value = 'Failed to load checklist. Please try again.';
        }
    } finally {
        logsLoading.value = false;
        checkPatrolCompletion();
    }
}

function saveLogsCache() {
    if (!props.activePatrol) return;
    localStorage.setItem(
        `patrol_logs_${props.activePatrol.id}`,
        JSON.stringify(logs.value),
    );
}

// Watch active patrol session changes
watch(
    () => props.activePatrol,
    (newVal) => {
        if (newVal) {
            routeDetails.value = newVal.route || null;
            loadPatrolLogs();
            checkPatrolCompletion();
        } else {
            logs.value = [];
            routeDetails.value = null;
        }
    },
    { immediate: true, deep: true },
);

// Check if all checkpoints are done
function checkPatrolCompletion() {
    if (logs.value.length === 0) {
        showCompletePanel.value = false;
        return;
    }
    const pending = logs.value.filter(
        (l) => l.status === 'pending' || l.status === 'out_of_order_attempt',
    );
    showCompletePanel.value = pending.length === 0;

    if (showCompletePanel.value) {
        setTimeout(() => {
            initSignature(completionSigCanvasRef.value);
        }, 100);
    }
}

// Helper to check if a checkpoint log is locked based on strict order enforcement
function isCheckpointLocked(log: CheckpointLog): boolean {
    if (!props.activePatrol || !routeDetails.value?.enforce_order) {
        return false;
    }
    // Find the first pending position
    const firstPending = logs.value.find(
        (l) => l.status === 'pending' || l.status === 'out_of_order_attempt',
    );
    if (!firstPending) return false;
    return log.position > firstPending.position;
}

// Find current interactive pending checkpoint (fixed operator precedence bug)
const getActiveLog = () => {
    return logs.value.find(
        (l) =>
            (l.status === 'pending' || l.status === 'out_of_order_attempt') &&
            !isCheckpointLocked(l),
    );
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
        script.onerror = () =>
            reject(new Error('Failed to load QR scanner library.'));
        document.head.appendChild(script);
    });
}

// Handle real scan trigger
async function startScan(method: 'qr' | 'nfc') {
    const activeLog = getActiveLog();
    if (!activeLog) return;

    // Photo required validation
    if (
        activeLog.checkpoint.photo_requirement === 'required' &&
        !checkpointPhoto.value
    ) {
        alert('Photo capture is required for this checkpoint.');
        return;
    }

    // Voice memo required validation
    if (
        activeLog.checkpoint.voice_requirement === 'required' &&
        !checkpointVoiceBlob.value
    ) {
        alert('Voice memo recording is required for this checkpoint.');
        return;
    }

    // GPS Geofence validation
    if (activeLog.checkpoint.gps_required) {
        const gps = await updateLocation();
        if (!gps) {
            alert(
                'GPS coordinates are required to verify your location for this checkpoint.',
            );
            return;
        }

        if (
            activeLog.checkpoint.latitude !== undefined &&
            activeLog.checkpoint.longitude !== undefined &&
            activeLog.checkpoint.latitude !== null &&
            activeLog.checkpoint.longitude !== null
        ) {
            const distance = calculateDistanceMetres(
                gps.latitude,
                gps.longitude,
                Number(activeLog.checkpoint.latitude),
                Number(activeLog.checkpoint.longitude),
            );

            if (distance > activeLog.checkpoint.gps_fence_radius) {
                alert(
                    `You are outside the required GPS geofence for this checkpoint.\nDistance: ${Math.round(distance)}m (Limit: ${activeLog.checkpoint.gps_fence_radius}m).`,
                );
                return;
            }
        } else {
            alert(
                'Checkpoint coordinates are missing. Cannot verify GPS location.',
            );
            return;
        }
    }

    // Signature required validation
    let sigData = null;
    if (activeLog.checkpoint.signature_required) {
        const canvas = sigCanvasRefs.value[activeLog.id];
        if (!canvas || isCanvasBlank(canvas)) {
            alert('Digital signature is required for this checkpoint.');
            return;
        }
        sigData = canvas.toDataURL('image/png');
    }

    currentSigData.value = sigData;
    simulatorMethod.value = method;

    const expectedCode = activeLog.checkpoint.qr_code || '';
    const expectedNfc = activeLog.checkpoint.nfc_tag_id || '';

    if (method === 'qr') {
        if (CapacitorBridge.isNative()) {
            try {
                const {
                    CapacitorBarcodeScanner,
                    CapacitorBarcodeScannerTypeHint,
                } = await import('@capacitor/barcode-scanner');
                const result = await CapacitorBarcodeScanner.scanBarcode({
                    hint: CapacitorBarcodeScannerTypeHint.QR_CODE,
                });
                if (result && result.ScanResult) {
                    const decodedText = result.ScanResult;
                    if (
                        decodedText.trim().toLowerCase() ===
                        expectedCode.trim().toLowerCase()
                    ) {
                        executeScan('qr', sigData, decodedText);
                    } else {
                        alert(
                            `Scanned code "${decodedText}" does not match the expected checkpoint QR code ("${expectedCode}").`,
                        );
                    }
                }
            } catch (e: any) {
                console.error('Capacitor Barcode Scanner failed:', e);
                alert('Failed to scan QR code: ' + e.message);
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
            html5QrCodeInstance = new (window as any).Html5Qrcode('qr-reader');
            const activeLog = getActiveLog();
            const expectedCode = activeLog?.checkpoint.qr_code || '';

            html5QrCodeInstance
                .start(
                    { facingMode: 'environment' },
                    {
                        fps: 10,
                        qrbox: { width: 220, height: 220 },
                    },
                    (decodedText: string) => {
                        if (
                            decodedText.trim().toLowerCase() ===
                            expectedCode.trim().toLowerCase()
                        ) {
                            stopQrScanner();
                            showScanSimulator.value = false;
                            executeScan('qr', signatureBase64, decodedText);
                        } else {
                            alert(
                                `Scanned code "${decodedText}" does not match the expected checkpoint QR code ("${expectedCode}").`,
                            );
                        }
                    },
                    (errorMessage: string) => {},
                )
                .catch((err: any) => {
                    console.error('Webcam init error:', err);
                    alert(
                        'Camera permission denied, or camera is currently occupied by another app.',
                    );
                    showScanSimulator.value = false;
                });
        } catch (e: any) {
            alert('QR Scanner initialization failed: ' + e.message);
            showScanSimulator.value = false;
        }
    }, 400);
}

// Stop webcam QR scanner
function stopQrScanner() {
    if (html5QrCodeInstance) {
        try {
            if (html5QrCodeInstance.isScanning) {
                html5QrCodeInstance
                    .stop()
                    .then(() => {
                        html5QrCodeInstance = null;
                    })
                    .catch((e: any) => {
                        console.error(
                            'html5QrCodeInstance stop callback error:',
                            e,
                        );
                        html5QrCodeInstance = null;
                    });
            } else {
                html5QrCodeInstance = null;
            }
        } catch (e) {
            console.error('html5QrCodeInstance stop error:', e);
            html5QrCodeInstance = null;
        }
    }
}

// Helper to decode NDEF Text Record payload
function decodeNdefTextRecord(payloadBytes: Uint8Array | number[]): string {
    if (!payloadBytes || payloadBytes.length === 0) return '';
    const bytes = Array.isArray(payloadBytes)
        ? new Uint8Array(payloadBytes)
        : payloadBytes;
    const statusByte = bytes[0];
    const isUtf16 = (statusByte & 0x80) !== 0;
    const languageCodeLength = statusByte & 0x3f;

    if (1 + languageCodeLength > bytes.length) {
        return new TextDecoder().decode(bytes);
    }

    const textBytes = bytes.subarray(1 + languageCodeLength);
    const decoder = new TextDecoder(isUtf16 ? 'utf-16' : 'utf-8');
    return decoder.decode(textBytes);
}

// Start Capacitor NFC Tag Reader
async function startCapacitorNfcReader(signatureBase64: string | null) {
    nfcStatusMessage.value =
        'Place the back of your phone directly on top of the NFC tag.';
    showNfcManualInput.value = false;
    nfcManualCode.value = '';

    const activeLog = getActiveLog();
    const expectedCode = activeLog?.checkpoint.nfc_tag_id || '';

    try {
        const { CapacitorNfc } = await import('@capgo/capacitor-nfc');
        const { supported } = await CapacitorNfc.isSupported();
        if (!supported) {
            nfcStatusMessage.value = 'NFC is not supported on this device.';
            showNfcManualInput.value = true;
            return;
        }

        await CapacitorNfc.startScanning({
            invalidateAfterFirstRead: true,
            alertMessage: 'Hold a tag near the device.',
        });

        const listener = await CapacitorNfc.addListener(
            'nfcEvent',
            (event: any) => {
                const tagIdBytes = event.tag?.id;
                let serialNumber = '';
                if (Array.isArray(tagIdBytes)) {
                    serialNumber = tagIdBytes
                        .map((b: number) => b.toString(16).padStart(2, '0'))
                        .join(':');
                }

                let scannedVal = serialNumber;
                let matched = false;

                if (
                    serialNumber &&
                    (serialNumber.toLowerCase() ===
                        expectedCode.toLowerCase() ||
                        serialNumber.replace(/:/g, '').toLowerCase() ===
                            expectedCode.toLowerCase())
                ) {
                    matched = true;
                }

                if (event.tag?.ndefMessage) {
                    for (const record of event.tag.ndefMessage) {
                        if (record.payload) {
                            try {
                                const isTextRecord =
                                    record.tnf === 1 &&
                                    Array.isArray(record.type) &&
                                    record.type.length === 1 &&
                                    record.type[0] === 84; // 84 is ASCII for 'T'

                                let decodedText = '';
                                if (isTextRecord) {
                                    decodedText = decodeNdefTextRecord(
                                        record.payload,
                                    );
                                } else {
                                    decodedText = new TextDecoder().decode(
                                        new Uint8Array(record.payload),
                                    );
                                }

                                if (
                                    decodedText.trim().toLowerCase() ===
                                    expectedCode.toLowerCase()
                                ) {
                                    matched = true;
                                    scannedVal = decodedText;
                                    break;
                                }
                            } catch (e) {
                                console.error(
                                    'Error parsing Capacitor NFC record payload:',
                                    e,
                                );
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
            },
        );

        (window as any).activeNfcListener = listener;
    } catch (error: any) {
        console.error('Capacitor NFC reading failed:', error);
        nfcStatusMessage.value =
            'NFC Reader is restricted or busy: ' + error.message;
        showNfcManualInput.value = true;
    }
}

// Start Web NFC tag reading session
async function startNfcTagReader(signatureBase64: string | null) {
    nfcStatusMessage.value =
        'Place the back of your phone directly on top of the NFC tag.';
    showNfcManualInput.value = false;
    nfcManualCode.value = '';

    const activeLog = getActiveLog();
    const expectedCode = activeLog?.checkpoint.nfc_tag_id || '';

    if ('NDEFReader' in window) {
        try {
            ndefAbortController = new AbortController();
            const ndef = new (window as any).NDEFReader();
            await ndef.scan({ signal: ndefAbortController.signal });

            ndef.addEventListener('readingerror', () => {
                nfcStatusMessage.value =
                    'Error reading NFC tag. Try repositioning the phone closer to the tag.';
            });

            ndef.addEventListener(
                'reading',
                ({ message, serialNumber }: any) => {
                    let matched = false;
                    let scannedVal = serialNumber || '';

                    if (
                        serialNumber &&
                        (serialNumber.toLowerCase() ===
                            expectedCode.toLowerCase() ||
                            serialNumber.replace(/:/g, '').toLowerCase() ===
                                expectedCode.toLowerCase())
                    ) {
                        matched = true;
                    }

                    // Parse records
                    if (message && message.records) {
                        for (const record of message.records) {
                            try {
                                let text = '';
                                if (record.recordType === 'text') {
                                    const payloadBytes = new Uint8Array(
                                        record.data.buffer,
                                    );
                                    text = decodeNdefTextRecord(payloadBytes);
                                } else {
                                    const textDecoder = new TextDecoder(
                                        record.encoding || 'utf-8',
                                    );
                                    text = textDecoder.decode(record.data);
                                }

                                if (
                                    text.trim().toLowerCase() ===
                                    expectedCode.toLowerCase()
                                ) {
                                    matched = true;
                                    scannedVal = text;
                                    break;
                                }
                            } catch (e) {
                                console.error(
                                    'Error parsing Web NFC record data:',
                                    e,
                                );
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
                },
            );
        } catch (error: any) {
            console.error('NFC reading failed:', error);
            nfcStatusMessage.value =
                'NFC Reader is restricted or busy: ' + error.message;
            showNfcManualInput.value = true;
        }
    } else {
        nfcStatusMessage.value =
            'Web NFC (NDEFReader) is not supported on this browser/device (iOS requires native wrappers).';
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
            console.error('Failed to stop Capacitor NFC scanning:', e);
        }
    }
}

function submitNfcManual(signatureBase64: string | null) {
    const activeLog = getActiveLog();
    const expectedCode = activeLog?.checkpoint.nfc_tag_id || '';
    if (
        nfcManualCode.value.trim().toLowerCase() === expectedCode.toLowerCase()
    ) {
        cleanupNfc();
        showScanSimulator.value = false;
        executeScan('nfc', signatureBase64, nfcManualCode.value.trim());
    } else {
        alert(
            `Entered code "${nfcManualCode.value}" does not match the expected tag ID.`,
        );
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

function calculateDistanceMetres(
    lat1: number,
    lon1: number,
    lat2: number,
    lon2: number,
): number {
    const R = 6371e3; // Earth radius in metres
    const phi1 = (lat1 * Math.PI) / 180;
    const phi2 = (lat2 * Math.PI) / 180;
    const deltaPhi = ((lat2 - lat1) * Math.PI) / 180;
    const deltaLambda = ((lon2 - lon1) * Math.PI) / 180;

    const a =
        Math.sin(deltaPhi / 2) * Math.sin(deltaPhi / 2) +
        Math.cos(phi1) *
            Math.cos(phi2) *
            Math.sin(deltaLambda / 2) *
            Math.sin(deltaLambda / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

    return R * c; // in metres
}

function calculateBearing(
    lat1: number,
    lon1: number,
    lat2: number,
    lon2: number,
): number {
    const phi1 = (lat1 * Math.PI) / 180;
    const phi2 = (lat2 * Math.PI) / 180;
    const deltaLambda = ((lon2 - lon1) * Math.PI) / 180;

    const y = Math.sin(deltaLambda) * Math.cos(phi2);
    const x =
        Math.cos(phi1) * Math.sin(phi2) -
        Math.sin(phi1) * Math.cos(phi2) * Math.cos(deltaLambda);

    const bearing = (Math.atan2(y, x) * 180) / Math.PI;
    return (bearing + 360) % 360;
}

function getBearingDirection(bearing: number): string {
    const directions = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'];
    const index = Math.round(bearing / 45) % 8;
    return directions[index];
}

const targetDistanceAndBearing = computed(() => {
    const activeLog = getActiveLog();
    if (!activeLog || !currentPosition.value) return null;

    const cp = activeLog.checkpoint;
    if (
        cp.latitude === undefined ||
        cp.longitude === undefined ||
        cp.latitude === null ||
        cp.longitude === null
    ) {
        return null;
    }

    const dist = calculateDistanceMetres(
        currentPosition.value.latitude,
        currentPosition.value.longitude,
        Number(cp.latitude),
        Number(cp.longitude),
    );

    const bear = calculateBearing(
        currentPosition.value.latitude,
        currentPosition.value.longitude,
        Number(cp.latitude),
        Number(cp.longitude),
    );

    return {
        distance: Math.round(dist),
        bearing: bear,
        direction: getBearingDirection(bear),
    };
});

const paceAndEta = computed(() => {
    if (!props.activePatrol || !logs.value || logs.value.length === 0)
        return null;

    const scannedLogs = logs.value
        .filter((l) => l.status === 'scanned' && l.scanned_at)
        .sort(
            (a, b) =>
                new Date(a.scanned_at!).getTime() -
                new Date(b.scanned_at!).getTime(),
        );

    let avgPaceSeconds = 300; // default 5 minutes
    if (scannedLogs.length >= 2) {
        let totalDiff = 0;
        for (let i = 1; i < scannedLogs.length; i++) {
            const t1 = new Date(scannedLogs[i - 1].scanned_at!).getTime();
            const t2 = new Date(scannedLogs[i].scanned_at!).getTime();
            totalDiff += (t2 - t1) / 1000;
        }
        avgPaceSeconds = totalDiff / (scannedLogs.length - 1);
    }

    const remainingCount = logs.value.filter(
        (l) => l.status === 'pending',
    ).length;
    const remainingTimeSeconds = remainingCount * avgPaceSeconds;

    const paceMin = Math.floor(avgPaceSeconds / 60);
    const paceSec = Math.round(avgPaceSeconds % 60);
    const paceStr = paceMin > 0 ? `${paceMin}m ${paceSec}s` : `${paceSec}s`;

    let etaStr = 'N/A';
    if (remainingCount === 0) {
        etaStr = 'Finished';
    } else {
        const etaMin = Math.ceil(remainingTimeSeconds / 60);
        etaStr = `~${etaMin} min`;
    }

    return {
        pace: paceStr,
        eta: etaStr,
        remainingCount,
    };
});

// Scan processing with real code verification
async function executeScan(
    method: 'qr' | 'nfc',
    signatureBase64: string | null,
    scannedCodeVal?: string,
) {
    const log = getActiveLog();
    if (!log || !props.activePatrol) return;

    currentLoadingId.value = log.id;

    // Retrieve device location
    const gps = await updateLocation();
    const lat = gps ? gps.latitude : 34.6712;
    const lon = gps ? gps.longitude : 33.0412;

    const expectedCode =
        method === 'qr'
            ? log.checkpoint.qr_code || 'DEMO-QR'
            : log.checkpoint.nfc_tag_id || 'DEMO-NFC';
    const scannedCode = scannedCodeVal || expectedCode;

    const payload = {
        scan_method: method,
        latitude: lat,
        longitude: lon,
        note: checkpointNote.value || null,
        scanned_code: scannedCode,
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
                const photoFile = base64ToFile(
                    checkpointPhoto.value,
                    'checkpoint_photo.jpg',
                );
                formData.append('media_file', photoFile);
            }
            if (checkpointVoiceBlob.value) {
                const voiceFile = new File(
                    [checkpointVoiceBlob.value],
                    'checkpoint_voice.wav',
                    { type: 'audio/wav' },
                );
                formData.append('voice_file', voiceFile);
            }
            if (signatureBase64) {
                const sigFile = base64ToFile(signatureBase64, 'signature.png');
                formData.append('signature_file', sigFile);
            }

            await axios.post(
                `/api/guard/patrols/${props.activePatrol.id}/checkpoints/${log.route_checkpoint_id}/scan`,
                formData,
                { headers: { 'Content-Type': 'multipart/form-data' } },
            );

            log.status = 'scanned';
            log.scanned_at = new Date().toISOString();
            log.scan_method_used = method;
            log.note = checkpointNote.value;
            emit('checkpoint-scanned');

            alert(
                `✅ SUCCESS: Checkpoint "${log.checkpoint.name}" scanned successfully.`,
            );
        } catch (e: any) {
            console.error('Direct scan failed, queuing offline:', e);
            await queueOfflineScan(log, payload, method, signatureBase64);
        }
    } else {
        await queueOfflineScan(log, payload, method, signatureBase64);
    }

    checkpointNote.value = '';
    checkpointPhoto.value = null;
    deleteVoiceRecording();
    clearSignature(sigCanvasRefs.value[log.id]);
    currentLoadingId.value = null;
    saveLogsCache();
    checkPatrolCompletion();
}

async function queueOfflineScan(
    log: CheckpointLog,
    payload: any,
    method: string,
    signatureBase64: string | null,
) {
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
            longitude: payload.longitude,
        });
    }

    if (checkpointVoiceBlob.value) {
        try {
            const base64Audio = await blobToBase64(checkpointVoiceBlob.value);
            addToQueue('checkpoint_media', {
                patrol_checkpoint_log_id: log.id,
                base64_data: base64Audio,
                filename: 'checkpoint_voice.wav',
                kind: 'voice_memo',
                mime_type: 'audio/wav',
                latitude: payload.latitude,
                longitude: payload.longitude,
            });
        } catch (err) {
            console.error(
                'Failed to convert checkpoint voice blob to base64:',
                err,
            );
        }
    }

    log.status = 'scanned';
    log.scanned_at = new Date().toISOString();
    log.scan_method_used = method;
    log.note = checkpointNote.value;
    emit('checkpoint-scanned');

    alert(
        `💾 OFFLINE SAVED: Checkpoint "${log.checkpoint.name}" scanned offline. It will synchronize automatically when connection is restored.`,
    );
}

async function executeSkip() {
    if (
        !skipModalCheckpoint.value ||
        !skipReason.value.trim() ||
        !props.activePatrol
    )
        return;

    const log = skipModalCheckpoint.value;
    currentLoadingId.value = log.id;

    const reason = skipReason.value;
    const payload = {
        patrol_id: props.activePatrol.id,
        route_checkpoint_id: log.route_checkpoint_id,
        status: 'skipped',
        skip_reason: reason,
    };

    if (isOnline.value) {
        try {
            await axios.post(
                `/api/guard/patrols/${props.activePatrol.id}/checkpoints/${log.route_checkpoint_id}/skip`,
                {
                    skip_reason: reason,
                },
            );
            log.status = 'skipped';
            log.skip_reason = reason;
            log.skipped_at = new Date().toISOString();
            emit('checkpoint-skipped');
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
    emit('checkpoint-skipped');
}

// Trigger photo capture, forcing native camera inside Capacitor, or using capture attribute in web
async function handleCapturePhoto(logId: number) {
    if (CapacitorBridge.isNative()) {
        try {
            const image = await Camera.getPhoto({
                quality: 85,
                allowEditing: false,
                resultType: CameraResultType.DataUrl,
                source: CameraSource.Camera, // STRICTLY FORCES THE CAMERA (disables gallery picker)
            });
            if (image && image.dataUrl) {
                checkpointPhoto.value = image.dataUrl;
            }
        } catch (error: any) {
            console.error('Capacitor camera failed:', error);
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
                const sigFile = base64ToFile(
                    sigData,
                    'completion_signature.png',
                );
                formData.append('completion_signature', sigFile);
            }

            await axios.post(
                `/api/guard/patrols/${props.activePatrol.id}/complete`,
                formData,
                {
                    headers: { 'Content-Type': 'multipart/form-data' },
                },
            );

            localStorage.removeItem(`patrol_logs_${props.activePatrol.id}`);
            emit('patrol-completed');
        } catch (e: any) {
            console.error(e);
            completionError.value =
                e.response?.data?.message ||
                'Failed to complete patrol session online.';
            queueOfflineCompletion(sigData, lat, lon);
        }
    } else {
        queueOfflineCompletion(sigData, lat, lon);
    }
}

function queueOfflineCompletion(
    signatureBase64: string | null,
    lat: number | null,
    lon: number | null,
) {
    const payload = {
        patrol_id: props.activePatrol.id,
        general_note: generalNote.value,
        completion_latitude: lat,
        completion_longitude: lon,
        signature_base64: signatureBase64,
    };
    localStorage.setItem(
        'patrol_offline_completion_pending',
        JSON.stringify(payload),
    );
    emit('patrol-completed');
}

onMounted(async () => {
    loadPatrolLogs();
    checkPatrolCompletion();

    watch(
        () => getActiveLog(),
        (newLog) => {
            if (newLog && newLog.checkpoint.signature_required) {
                setTimeout(() => {
                    initSignature(sigCanvasRefs.value[newLog.id]);
                }, 100);
            }
        },
        { immediate: true },
    );

    // Initialize map
    try {
        await loadLeaflet();
        isMapLoaded.value = true;
        initMap();
    } catch (err: any) {
        console.error('Failed to load Leaflet map:', err);
        mapLoadError.value = err.message || 'Map failed to load';
    }

    watch(
        () => currentPosition.value,
        () => {
            if (isMapLoaded.value && mapInstance) {
                renderMapLayers();
            }
        },
    );

    watch(
        () => logs.value,
        () => {
            if (isMapLoaded.value && mapInstance) {
                renderMapLayers();
            }
        },
        { deep: true },
    );
});

onUnmounted(() => {
    if (mapInstance) {
        mapInstance.remove();
        mapInstance = null;
    }
});
</script>

<template>
    <div class="space-y-6">
        <!-- If no active patrol -->
        <div
            v-if="!activePatrol"
            class="animate-fadeIn rounded-3xl border border-slate-800/80 bg-slate-900/40 p-10 py-16 text-center"
        >
            <div
                class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full border border-slate-800 bg-slate-900"
            >
                <svg
                    class="h-8 w-8 text-slate-600"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                    ></path>
                </svg>
            </div>
            <h3 class="text-base font-bold text-slate-300">
                No Active Patrol Shift
            </h3>
            <p
                class="mx-auto mt-2 max-w-xs text-xs leading-relaxed text-slate-500"
            >
                Please go to the Home tab, select one of your assigned patrol
                routes, and start shift to tracking checkpoints.
            </p>
            <button
                @click="emit('navigate', 'dashboard')"
                class="mt-6 rounded-xl bg-indigo-600 px-5 py-3 text-xs font-bold uppercase tracking-wider text-white shadow-md transition-all hover:bg-indigo-500 active:scale-95"
            >
                View Patrol Routes
            </button>
        </div>

        <!-- If active patrol is present -->
        <div v-else class="animate-fadeIn space-y-6">
            <!-- Patrol Route Info Bar -->
            <div
                class="border-slate-850 flex items-center justify-between rounded-2xl border bg-slate-900 p-4"
            >
                <div>
                    <h3 class="text-sm font-bold text-slate-100">
                        {{
                            routeDetails?.name ||
                            activePatrol.route?.name ||
                            'Port Security Shift'
                        }}
                    </h3>
                    <p class="mt-0.5 text-[10px] text-slate-500">
                        Enforced Order:
                        {{
                            routeDetails?.enforce_order
                                ? 'Strict Sequential'
                                : 'Flexible'
                        }}
                    </p>
                </div>
                <div class="text-right">
                    <span
                        class="rounded-lg border border-indigo-500/20 bg-indigo-500/10 px-2.5 py-1 font-mono text-[10px] font-bold text-indigo-400"
                    >
                        {{ activePatrol.completed_checkpoints }}/{{
                            activePatrol.total_checkpoints
                        }}
                        Checked
                    </span>
                </div>
            </div>

            <!-- Unified Patrol Stats & Offline Sync Dashboard -->
            <div class="grid grid-cols-2 gap-3">
                <!-- Pace and ETA Widget -->
                <div
                    v-if="paceAndEta"
                    class="border-slate-850 flex flex-col justify-between space-y-2 rounded-2xl border bg-slate-900/60 p-3.5"
                >
                    <span
                        class="text-[9px] font-black uppercase tracking-widest text-slate-500"
                        >Patrol Progress Pace</span
                    >
                    <div class="flex items-baseline space-x-1.5">
                        <span class="text-base font-black text-slate-100">{{
                            paceAndEta.eta
                        }}</span>
                        <span class="text-[10px] text-slate-500"
                            >remaining</span
                        >
                    </div>
                    <div
                        class="flex items-center justify-between text-[10px] text-slate-400"
                    >
                        <span
                            >Avg Pace:
                            <span class="font-mono font-bold text-indigo-400">{{
                                paceAndEta.pace
                            }}</span></span
                        >
                        <span
                            >Pending:
                            <span class="font-mono font-bold text-indigo-400">{{
                                paceAndEta.remainingCount
                            }}</span></span
                        >
                    </div>
                </div>

                <!-- Offline Sync Widget -->
                <div
                    class="border-slate-850 flex flex-col justify-between space-y-2 rounded-2xl border bg-slate-900/60 p-3.5"
                >
                    <div class="flex items-center justify-between">
                        <span
                            class="text-[9px] font-black uppercase tracking-widest text-slate-500"
                            >Sync Status</span
                        >
                        <!-- Connection badge -->
                        <span
                            class="flex items-center space-x-1 rounded-full px-2 py-0.5 text-[9px] font-bold"
                            :class="
                                isOnline
                                    ? 'border border-emerald-500/20 bg-emerald-500/10 text-emerald-400'
                                    : 'border border-amber-500/20 bg-amber-500/10 text-amber-400'
                            "
                        >
                            <span
                                class="h-1.5 w-1.5 rounded-full"
                                :class="
                                    isOnline ? 'bg-emerald-500' : 'bg-amber-500'
                                "
                            ></span>
                            <span>{{ isOnline ? 'Online' : 'Offline' }}</span>
                        </span>
                    </div>

                    <div class="flex items-baseline space-x-1.5">
                        <span class="text-base font-black text-slate-100">{{
                            queue.length
                        }}</span>
                        <span class="text-[10px] text-slate-500"
                            >pending sync</span
                        >
                    </div>

                    <!-- Sync trigger button -->
                    <button
                        v-if="queue.length > 0 && isOnline"
                        @click="triggerSync"
                        :disabled="isSyncing"
                        class="flex items-center justify-center space-x-1.5 rounded-xl bg-indigo-600 px-3 py-1.5 text-[10px] font-bold text-white shadow transition-all hover:bg-indigo-500 active:scale-95 disabled:opacity-50"
                    >
                        <div
                            v-if="isSyncing"
                            class="h-3 w-3 animate-spin rounded-full border border-white/30 border-t-white"
                        ></div>
                        <span>{{ isSyncing ? 'Syncing...' : 'Sync Now' }}</span>
                    </button>
                    <div v-else class="text-[9px] italic text-slate-500">
                        {{
                            queue.length > 0
                                ? 'Will sync when online'
                                : 'All data synced'
                        }}
                    </div>
                </div>
            </div>

            <!-- CHECKPOINT STEP LOG LIST -->
            <div class="space-y-3">
                <div class="flex items-center justify-between pl-1">
                    <h4
                        class="text-xs font-black uppercase tracking-wider text-slate-400"
                    >
                        Route Checklist
                    </h4>
                    <button
                        v-if="!logsLoading"
                        @click="loadPatrolLogs"
                        class="flex items-center gap-1 rounded-lg border border-indigo-500/10 bg-indigo-500/5 px-2 py-1 text-[10px] font-bold text-indigo-400 transition-all hover:text-indigo-300"
                        title="Refresh checkpoint status from server"
                    >
                        <svg
                            class="h-3 w-3"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                            />
                        </svg>
                        Refresh
                    </button>
                </div>

                <!-- Live Checkpoint Proximity Map -->
                <div v-if="activePatrol" class="space-y-2">
                    <div
                        ref="patrolMapContainer"
                        class="border-slate-850 relative z-10 h-[200px] w-full overflow-hidden rounded-2xl border bg-slate-950"
                    >
                        <div
                            v-if="!isMapLoaded && !mapLoadError"
                            class="absolute inset-0 flex items-center justify-center text-xs text-slate-500"
                        >
                            Loading Map Assets...
                        </div>
                        <div
                            v-if="mapLoadError"
                            class="absolute inset-0 flex items-center justify-center p-4 text-center text-xs text-rose-500"
                        >
                            {{ mapLoadError }}
                        </div>
                    </div>
                </div>

                <!-- Loading logs -->
                <div
                    v-if="logsLoading"
                    class="flex items-center justify-center gap-3 py-10 text-slate-500"
                >
                    <div
                        class="h-5 w-5 animate-spin rounded-full border-2 border-indigo-500/30 border-t-indigo-500"
                    ></div>
                    <span class="text-xs">Loading checkpoint data...</span>
                </div>

                <!-- Error state -->
                <div
                    v-else-if="logsError"
                    class="space-y-3 rounded-2xl border border-rose-500/20 bg-rose-500/10 px-4 py-5 text-center"
                >
                    <p class="text-xs text-rose-400">{{ logsError }}</p>
                    <button
                        @click="loadPatrolLogs"
                        class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white transition-all hover:bg-indigo-500 active:scale-95"
                    >
                        Retry
                    </button>
                </div>

                <template v-else>
                    <div
                        v-for="log in logs"
                        :key="log.id"
                        class="rounded-2xl border bg-slate-900 p-4 transition-all duration-200"
                        :class="[
                            log.status === 'scanned'
                                ? 'border-emerald-500/20 bg-emerald-950/5'
                                : '',
                            log.status === 'skipped'
                                ? 'border-amber-500/20 bg-amber-950/5'
                                : '',
                            log.status === 'pending' && !isCheckpointLocked(log)
                                ? 'border-indigo-500/30 ring-1 ring-indigo-500/20'
                                : 'border-slate-850',
                            isCheckpointLocked(log)
                                ? 'select-none opacity-40'
                                : '',
                        ]"
                    >
                        <div class="flex items-start justify-between">
                            <div class="flex items-start space-x-3">
                                <!-- Number/Status badge -->
                                <div
                                    class="mt-0.5 flex h-6 w-6 items-center justify-center rounded-lg border font-mono text-xs font-bold"
                                    :class="[
                                        log.status === 'scanned'
                                            ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-400'
                                            : '',
                                        log.status === 'skipped'
                                            ? 'border-amber-500/30 bg-amber-500/10 text-amber-400'
                                            : '',
                                        log.status === 'pending' &&
                                        !isCheckpointLocked(log)
                                            ? 'border-indigo-500/30 bg-indigo-500/10 text-indigo-400'
                                            : 'border-slate-800 bg-slate-950 text-slate-600',
                                    ]"
                                >
                                    <svg
                                        v-if="log.status === 'scanned'"
                                        class="h-3.5 w-3.5"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="3"
                                            d="M5 13l4 4L19 7"
                                        />
                                    </svg>
                                    <svg
                                        v-else-if="log.status === 'skipped'"
                                        class="h-3.5 w-3.5"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                                        />
                                    </svg>
                                    <span v-else>{{ log.position }}</span>
                                </div>

                                <div>
                                    <h5
                                        class="text-xs font-bold"
                                        :class="
                                            log.status === 'scanned'
                                                ? 'text-slate-300'
                                                : 'text-slate-100'
                                        "
                                    >
                                        {{ log.checkpoint.name }}
                                    </h5>
                                    <p
                                        class="mt-1 text-[11px] leading-relaxed text-slate-500"
                                    >
                                        {{ log.checkpoint.description }}
                                    </p>
                                </div>
                            </div>

                            <!-- Lock or Skip action -->
                            <div class="flex items-center space-x-2">
                                <span
                                    v-if="isCheckpointLocked(log)"
                                    class="text-slate-600"
                                    title="Locked by order sequence"
                                >
                                    <svg
                                        class="h-4 w-4"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
                                        />
                                    </svg>
                                </span>
                                <!-- Skip Button -->
                                <button
                                    v-else-if="
                                        log.status === 'pending' &&
                                        routeDetails?.allow_skip
                                    "
                                    @click="skipModalCheckpoint = log"
                                    class="rounded-lg border border-amber-500/10 bg-amber-500/5 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-amber-500 hover:text-amber-400 active:scale-95"
                                >
                                    Skip
                                </button>
                            </div>
                        </div>

                        <!-- EXPANDED SCAN UTILITY (For Current Active Checkpoint) -->
                        <div
                            v-if="
                                log.status === 'pending' &&
                                !isCheckpointLocked(log)
                            "
                            class="animate-fadeIn mt-4 space-y-4 border-t border-slate-800/80 pt-4"
                        >
                            <!-- Checkpoint Requirements tags -->
                            <div
                                class="flex flex-wrap gap-1.5 text-[9px] font-bold text-slate-400"
                            >
                                <span
                                    class="flex items-center rounded border border-slate-800 bg-slate-950 px-2 py-0.5"
                                >
                                    METHOD:
                                    {{
                                        log.checkpoint.scan_method.toUpperCase()
                                    }}
                                </span>
                                <span
                                    v-if="
                                        log.checkpoint.photo_requirement !==
                                        'off'
                                    "
                                    class="flex items-center rounded border px-2 py-0.5"
                                    :class="
                                        log.checkpoint.photo_requirement ===
                                        'required'
                                            ? 'border-indigo-500/20 bg-indigo-500/10 text-indigo-400'
                                            : 'border-slate-800 bg-slate-950 text-slate-400'
                                    "
                                >
                                    PHOTO:
                                    {{
                                        log.checkpoint.photo_requirement.toUpperCase()
                                    }}
                                </span>
                                <span
                                    v-if="log.checkpoint.signature_required"
                                    class="flex items-center rounded border border-indigo-500/20 bg-indigo-500/10 px-2 py-0.5 text-indigo-400"
                                >
                                    SIGNATURE REQUIRED
                                </span>
                                <span
                                    v-if="
                                        log.checkpoint.voice_requirement !==
                                        'off'
                                    "
                                    class="flex items-center rounded border px-2 py-0.5"
                                    :class="
                                        log.checkpoint.voice_requirement ===
                                        'required'
                                            ? 'border-indigo-500/20 bg-indigo-500/10 text-indigo-400'
                                            : 'border-slate-800 bg-slate-950 text-slate-400'
                                    "
                                >
                                    VOICE:
                                    {{
                                        log.checkpoint.voice_requirement.toUpperCase()
                                    }}
                                </span>
                            </div>

                            <!-- Live Proximity Compass & Distance -->
                            <div
                                v-if="targetDistanceAndBearing"
                                class="flex items-center space-x-3 rounded-xl border border-indigo-500/10 bg-indigo-500/5 p-3 text-xs"
                            >
                                <div
                                    class="relative flex h-8 w-8 items-center justify-center rounded-full bg-indigo-500/10 text-indigo-400"
                                >
                                    <svg
                                        class="h-4 w-4 transition-transform duration-300"
                                        :style="{
                                            transform: `rotate(${targetDistanceAndBearing.bearing}deg)`,
                                        }"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="3"
                                            d="M12 19V5m0 0l-7 7m7-7l7 7"
                                        />
                                    </svg>
                                </div>
                                <div class="flex-grow">
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <span class="font-bold text-slate-200"
                                            >Next Checkpoint Proximity</span
                                        >
                                        <span
                                            class="font-mono text-sm font-black text-indigo-400"
                                        >
                                            {{
                                                targetDistanceAndBearing.distance
                                            }}m
                                        </span>
                                    </div>
                                    <div
                                        class="mt-0.5 flex items-center justify-between text-[10px] text-slate-500"
                                    >
                                        <span
                                            >Bearing:
                                            {{
                                                targetDistanceAndBearing.direction
                                            }}
                                            ({{
                                                Math.round(
                                                    targetDistanceAndBearing.bearing,
                                                )
                                            }}°)</span
                                        >
                                        <span v-if="log.checkpoint.gps_required"
                                            >Required Zone:
                                            {{
                                                log.checkpoint.gps_fence_radius
                                            }}m</span
                                        >
                                    </div>
                                </div>
                            </div>

                            <!-- Optional Note Input -->
                            <div>
                                <textarea
                                    v-model="checkpointNote"
                                    rows="2"
                                    class="border-slate-850 w-full rounded-xl border bg-slate-950 p-2.5 text-xs text-slate-100 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                    placeholder="Add notes (optional)..."
                                ></textarea>
                            </div>

                            <!-- Real Photo Capture Camera Hook -->
                            <div
                                v-if="
                                    log.checkpoint.photo_requirement !== 'off'
                                "
                                class="space-y-2"
                            >
                                <div class="flex items-center space-x-3">
                                    <input
                                        type="file"
                                        :ref="
                                            (el) => {
                                                if (el)
                                                    photoInputRefs[log.id] =
                                                        el as any;
                                            }
                                        "
                                        accept="image/*"
                                        capture="environment"
                                        class="hidden"
                                        @change="handlePhotoCaptured"
                                    />
                                    <button
                                        @click="handleCapturePhoto(log.id)"
                                        class="bg-slate-850 flex items-center space-x-2 rounded-xl border border-slate-800 px-4 py-2.5 text-xs font-bold text-slate-200 transition-all hover:bg-slate-800 active:scale-95"
                                    >
                                        <svg
                                            class="h-4 w-4 text-indigo-400"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"
                                            />
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"
                                            />
                                        </svg>
                                        <span>Capture Checkpoint Photo</span>
                                    </button>
                                    <span
                                        v-if="checkpointPhoto"
                                        class="flex items-center space-x-1 text-[10px] font-bold text-emerald-400"
                                    >
                                        <svg
                                            class="h-3.5 w-3.5"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="3"
                                                d="M5 13l4 4L19 7"
                                            />
                                        </svg>
                                        <span>Photo Captured</span>
                                    </span>
                                </div>
                                <div
                                    v-if="checkpointPhoto"
                                    class="aspect-video w-24 overflow-hidden rounded-lg border border-slate-800 bg-slate-950"
                                >
                                    <img
                                        :src="checkpointPhoto"
                                        class="h-full w-full object-cover"
                                    />
                                </div>
                            </div>

                            <!-- Checkpoint Voice Memo Recording -->
                            <div
                                v-if="
                                    log.checkpoint.voice_requirement !== 'off'
                                "
                                class="space-y-2 border-t border-slate-800/60 pt-3"
                            >
                                <label
                                    class="block text-[10px] font-bold uppercase tracking-wider text-slate-400"
                                >
                                    Audio Evidence (Voice Memo)
                                    <span
                                        v-if="
                                            log.checkpoint.voice_requirement ===
                                            'required'
                                        "
                                        class="text-rose-500"
                                        >*</span
                                    >
                                </label>
                                <div class="flex items-center space-x-3">
                                    <!-- Record Button -->
                                    <button
                                        v-if="
                                            !isVoiceRecording &&
                                            !checkpointVoiceUrl
                                        "
                                        @click="startVoiceRecording"
                                        type="button"
                                        class="bg-violet-650 flex items-center space-x-2 rounded-xl px-4 py-2.5 text-xs font-bold text-white shadow-md transition-all hover:bg-violet-600 active:scale-95"
                                    >
                                        <svg
                                            class="h-4 w-4 text-white"
                                            fill="currentColor"
                                            viewBox="0 0 20 20"
                                        >
                                            <path
                                                fill-rule="evenodd"
                                                d="M7 4a3 3 0 016 0v4a3 3 0 11-6 0V4zm3 10a5.964 5.964 0 003.9-1.5 1 1 0 011.4 1.4A7.964 7.964 0 0111 15.9V18a1 1 0 11-2 0v-2.1a7.965 7.965 0 01-5.3-2.0 1 1 0 111.4-1.4 5.963 5.963 0 003.9 1.5z"
                                                clip-rule="evenodd"
                                            />
                                        </svg>
                                        <span>Record Voice Memo</span>
                                    </button>

                                    <!-- Recording Status -->
                                    <div
                                        v-else-if="isVoiceRecording"
                                        class="flex items-center space-x-3 rounded-xl border border-rose-500/30 bg-rose-500/15 px-4 py-2 text-rose-400"
                                    >
                                        <span class="relative flex h-2 w-2">
                                            <span
                                                class="absolute inline-flex h-full w-full animate-ping rounded-full bg-rose-400 opacity-75"
                                            ></span>
                                            <span
                                                class="relative inline-flex h-2 w-2 rounded-full bg-rose-500"
                                            ></span>
                                        </span>
                                        <span
                                            class="font-mono text-xs font-bold"
                                            >Recording:
                                            {{ voiceRecordingDuration }}s</span
                                        >
                                        <button
                                            @click="stopVoiceRecording"
                                            type="button"
                                            class="rounded-lg bg-rose-600 px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-white transition-all hover:bg-rose-500 active:scale-95"
                                        >
                                            Stop
                                        </button>
                                    </div>

                                    <!-- Playback Preview -->
                                    <div
                                        v-else-if="checkpointVoiceUrl"
                                        class="border-slate-850 flex w-full items-center justify-between rounded-2xl border bg-slate-950 p-2.5"
                                    >
                                        <audio
                                            :src="checkpointVoiceUrl"
                                            controls
                                            class="h-8 max-w-[240px]"
                                        ></audio>
                                        <button
                                            @click="deleteVoiceRecording"
                                            type="button"
                                            class="rounded-xl border border-rose-500/10 bg-rose-500/5 px-3 py-2 text-[10px] font-black uppercase tracking-widest text-rose-500 transition-all hover:text-rose-400 active:scale-95"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Dynamic Signature Canvas Box -->
                            <div
                                v-if="log.checkpoint.signature_required"
                                class="space-y-2"
                            >
                                <label
                                    class="block text-[10px] font-bold uppercase tracking-wider text-slate-400"
                                    >Digital Sign-off</label
                                >
                                <div
                                    class="border-slate-850 relative overflow-hidden rounded-xl border bg-slate-950"
                                >
                                    <canvas
                                        :ref="
                                            (el) => {
                                                if (el)
                                                    sigCanvasRefs[log.id] =
                                                        el as any;
                                            }
                                        "
                                        width="320"
                                        height="120"
                                        class="h-[120px] w-full cursor-crosshair touch-none bg-slate-950"
                                    ></canvas>
                                    <button
                                        @click="
                                            clearSignature(
                                                sigCanvasRefs[log.id],
                                            )
                                        "
                                        class="absolute bottom-2 right-2 rounded border border-slate-800 bg-slate-900/80 px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-slate-400 backdrop-blur"
                                    >
                                        Clear
                                    </button>
                                </div>
                            </div>

                            <!-- Interactive Scan Buttons -->
                            <div class="grid grid-cols-2 gap-3">
                                <button
                                    v-if="
                                        log.checkpoint.scan_method === 'qr' ||
                                        log.checkpoint.scan_method === 'both'
                                    "
                                    @click="startScan('qr')"
                                    :disabled="currentLoadingId === log.id"
                                    class="flex items-center justify-center space-x-2 rounded-xl bg-indigo-600 py-3.5 text-xs font-bold uppercase tracking-wider text-white shadow-md transition-all hover:bg-indigo-500 active:scale-95"
                                >
                                    <svg
                                        class="h-4 w-4"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"
                                        />
                                    </svg>
                                    <span>Scan QR Code</span>
                                </button>

                                <button
                                    v-if="
                                        log.checkpoint.scan_method === 'nfc' ||
                                        log.checkpoint.scan_method === 'both'
                                    "
                                    @click="startScan('nfc')"
                                    :disabled="currentLoadingId === log.id"
                                    class="bg-violet-650 flex items-center justify-center space-x-2 rounded-xl py-3.5 text-xs font-bold uppercase tracking-wider text-white shadow-md transition-all hover:bg-violet-600 active:scale-95"
                                    :class="
                                        log.checkpoint.scan_method === 'nfc'
                                            ? 'col-span-2'
                                            : ''
                                    "
                                >
                                    <svg
                                        class="h-4 w-4"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"
                                        />
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
                class="animate-fadeIn space-y-4 rounded-3xl border border-emerald-500/20 bg-slate-900 bg-gradient-to-br from-slate-900 via-slate-900 to-emerald-950/20 p-5 shadow-xl"
            >
                <div class="flex items-center space-x-2 text-emerald-400">
                    <svg
                        class="h-5 w-5 animate-bounce"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                    <h4 class="text-sm font-bold uppercase tracking-wider">
                        Patrol Route Completed
                    </h4>
                </div>
                <p class="text-xs leading-relaxed text-slate-400">
                    All route checkpoints have been checked or skipped. Please
                    sign off and submit the patrol report below.
                </p>

                <!-- General Shift Note -->
                <div>
                    <label
                        class="mb-2 block text-[10px] font-bold uppercase tracking-wider text-slate-400"
                        >General Patrol Notes</label
                    >
                    <textarea
                        v-model="generalNote"
                        rows="3"
                        class="border-slate-850 w-full rounded-xl border bg-slate-950 p-2.5 text-xs text-slate-100 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        placeholder="Provide details about gates, security observations, shift occurrences..."
                    ></textarea>
                </div>

                <!-- Complete Sign-off signature pad -->
                <div class="space-y-2">
                    <label
                        class="block text-[10px] font-bold uppercase tracking-wider text-slate-400"
                        >Guard Final Signature</label
                    >
                    <div
                        class="border-slate-850 relative overflow-hidden rounded-xl border bg-slate-950"
                    >
                        <canvas
                            ref="completionSigCanvasRef"
                            width="320"
                            height="130"
                            class="h-[130px] w-full cursor-crosshair touch-none bg-slate-950"
                        ></canvas>
                        <button
                            @click="clearSignature(completionSigCanvasRef)"
                            class="absolute bottom-2 right-2 rounded border border-slate-800 bg-slate-900/80 px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-slate-400 backdrop-blur"
                        >
                            Clear
                        </button>
                    </div>
                </div>

                <div
                    v-if="completionError"
                    class="rounded-xl bg-rose-500/10 p-2.5 text-center text-xs font-medium text-rose-400"
                >
                    {{ completionError }}
                </div>

                <button
                    @click="handleCompletePatrol"
                    :disabled="completionLoading"
                    class="hover:to-teal-850 active:scale-98 flex w-full items-center justify-center space-x-2 rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-700 py-4 text-xs font-bold uppercase tracking-widest text-white shadow-xl shadow-emerald-600/20 transition-all hover:from-emerald-700"
                >
                    <span
                        v-if="completionLoading"
                        class="h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white"
                    ></span>
                    <span v-else>SUBMIT PATROL REPORT</span>
                </button>
            </div>
        </div>

        <!-- SKIP REASON MODAL -->
        <div
            v-if="skipModalCheckpoint"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-6 backdrop-blur-sm"
        >
            <div
                class="animate-fadeIn w-full max-w-sm space-y-4 rounded-3xl border border-slate-800 bg-slate-900 p-6 shadow-2xl"
            >
                <h4 class="text-sm font-bold text-slate-100">
                    Skip Checkpoint: {{ skipModalCheckpoint.checkpoint.name }}
                </h4>
                <p class="text-xs text-slate-400">
                    Please provide a valid security reason for skipping this
                    checkpoint. This skip log will be recorded in the audit
                    history.
                </p>

                <textarea
                    v-model="skipReason"
                    rows="3"
                    class="border-slate-850 w-full rounded-xl border bg-slate-950 p-2.5 text-xs text-slate-100 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                    placeholder="E.g. Access blocked by cargo, gate keys missing, area locked by facility manager..."
                ></textarea>

                <div class="flex space-x-3">
                    <button
                        @click="
                            skipModalCheckpoint = null;
                            skipReason = '';
                        "
                        class="hover:bg-slate-750 flex-1 rounded-xl bg-slate-800 py-3 text-xs font-bold uppercase tracking-wider text-slate-300 active:scale-95"
                    >
                        Cancel
                    </button>
                    <button
                        @click="executeSkip"
                        :disabled="!skipReason.trim()"
                        class="flex-1 rounded-xl bg-amber-600 py-3 text-xs font-bold uppercase tracking-wider text-white shadow-lg shadow-amber-600/20 hover:bg-amber-500 active:scale-95 disabled:pointer-events-none disabled:opacity-40"
                    >
                        Confirm Skip
                    </button>
                </div>
            </div>
        </div>

        <!-- SCAN OVERLAY / SCANNER SCREEN -->
        <div
            v-if="showScanSimulator"
            class="fixed inset-0 z-50 flex flex-col items-center justify-center bg-slate-950/95 p-6"
        >
            <div
                v-if="simulatorMethod === 'qr'"
                class="flex w-full max-w-sm flex-col items-center space-y-6"
            >
                <h4
                    class="text-sm font-bold uppercase tracking-widest text-indigo-400"
                >
                    Scan Checkpoint QR Code
                </h4>
                <p class="text-center text-xs text-slate-400">
                    Place the QR code inside the frame to scan automatically.
                </p>

                <!-- Webcam reader target -->
                <div
                    id="qr-reader"
                    class="relative h-64 w-64 overflow-hidden rounded-3xl border border-indigo-500/30 bg-black"
                >
                    <!-- Frame border effect -->
                    <div
                        class="pointer-events-none absolute inset-4 z-10 rounded-2xl border border-dashed border-indigo-500/40"
                    ></div>
                </div>

                <button
                    @click="
                        stopQrScanner();
                        showScanSimulator = false;
                    "
                    class="hover:bg-slate-850 rounded-xl border border-slate-800 bg-slate-900 px-6 py-3 text-xs font-bold uppercase tracking-wider text-slate-300 active:scale-95"
                >
                    Cancel Scan
                </button>
            </div>

            <div
                v-else
                class="flex w-full max-w-sm flex-col items-center space-y-6 text-center"
            >
                <!-- NFC icon/pulse -->
                <div
                    class="mb-4 flex h-24 w-24 animate-pulse items-center justify-center rounded-full border border-indigo-500/20 bg-indigo-500/10"
                >
                    <svg
                        class="h-12 w-12 text-indigo-400"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="1.5"
                            d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"
                        />
                    </svg>
                </div>

                <h4
                    class="text-sm font-bold uppercase tracking-widest text-indigo-400"
                >
                    NFC Tag Reader
                </h4>
                <p class="px-4 text-xs leading-relaxed text-slate-300">
                    {{ nfcStatusMessage }}
                </p>

                <!-- Manual fallback input if Web NFC not supported -->
                <div
                    v-if="showNfcManualInput"
                    class="w-full space-y-3 border-t border-slate-900 px-4 pt-4"
                >
                    <p
                        class="text-[10px] font-bold uppercase tracking-wider text-slate-500"
                    >
                        Manual Fallback Code Entry
                    </p>
                    <input
                        v-model="nfcManualCode"
                        type="text"
                        class="w-full rounded-xl border border-slate-800 bg-slate-900 p-3 text-center font-mono text-xs uppercase tracking-widest text-slate-100 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        placeholder="Type NFC code..."
                    />
                    <button
                        @click="submitNfcManual(currentSigData)"
                        :disabled="!nfcManualCode.trim()"
                        class="w-full rounded-xl bg-indigo-600 py-3 text-xs font-bold uppercase tracking-wider text-white transition-all hover:bg-indigo-500 active:scale-95 disabled:pointer-events-none disabled:opacity-40"
                    >
                        Submit Tag ID
                    </button>
                </div>

                <button
                    @click="
                        cleanupNfc();
                        showScanSimulator = false;
                    "
                    class="hover:bg-slate-850 rounded-xl border border-slate-800 bg-slate-900 px-6 py-3 text-xs font-bold uppercase tracking-wider text-slate-300 active:scale-95"
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
    0% {
        top: 0%;
    }
    50% {
        top: 100%;
    }
    100% {
        top: 0%;
    }
}
</style>
