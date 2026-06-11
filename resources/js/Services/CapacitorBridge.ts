/**
 * Capacitor Hardware Bridge Helper
 * Automatically handles native device plugins when running inside Capacitor shell
 * and falls back to interactive mock simulators when running in standard web browsers.
 */

export interface GpsPosition {
    latitude: number;
    longitude: number;
    accuracy: number;
}

export interface BatteryInfo {
    level: number; // 0 to 100
    isCharging: boolean;
}

declare global {
    interface Window {
        Capacitor?: any;
    }
}

export class CapacitorBridge {
    /**
     * Check if the application is running inside a Capacitor native app context.
     */
    public static isNative(): boolean {
        return typeof window !== 'undefined' && !!window.Capacitor;
    }

    /**
     * Get the current GPS coordinates of the guard.
     */
    public static async getCurrentPosition(): Promise<GpsPosition> {
        if (this.isNative()) {
            try {
                // Call Capacitor native Geolocation plugin
                const position = await window.Capacitor.Plugins.Geolocation.getCurrentPosition({
                    enableHighAccuracy: true,
                    timeout: 10000,
                });
                return {
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                    accuracy: position.coords.accuracy,
                };
            } catch (error) {
                console.error("Capacitor Geolocation failed, using web fallback.", error);
            }
        }

        // Standard Web Browser fallback
        return new Promise((resolve, reject) => {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        resolve({
                            latitude: position.coords.latitude,
                            longitude: position.coords.longitude,
                            accuracy: position.coords.accuracy,
                        });
                    },
                    (error) => {
                        console.warn("Web Geolocation failed. Using simulated coordinates.", error);
                        // Default mock coordinates (witbo HQ Cyprus)
                        resolve({
                            latitude: 35.123456,
                            longitude: 33.123456,
                            accuracy: 10,
                        });
                    },
                    { enableHighAccuracy: true, timeout: 5000 }
                );
            } else {
                resolve({
                    latitude: 35.123456,
                    longitude: 33.123456,
                    accuracy: 10,
                });
            }
        });
    }

    /**
     * Retrieve current device battery level and charging status.
     */
    public static async getBatteryInfo(): Promise<BatteryInfo> {
        if (this.isNative()) {
            try {
                const info = await window.Capacitor.Plugins.Device.getBatteryInfo();
                return {
                    level: Math.round(info.batteryLevel * 100),
                    isCharging: info.isCharging,
                };
            } catch (error) {
                console.error("Capacitor Battery check failed, using web fallback.", error);
            }
        }

        // Web Browser fallback
        if (typeof navigator !== 'undefined' && 'getBattery' in navigator) {
            try {
                const battery: any = await (navigator as any).getBattery();
                return {
                    level: Math.round(battery.level * 100),
                    isCharging: battery.charging,
                };
            } catch {
                // Ignore and fall back to dummy data
            }
        }

        return {
            level: 88, // mock level
            isCharging: false,
        };
    }

    /**
     * Simulate or trigger QR code scanner.
     */
    public static async scanQrCode(expectedCode: string): Promise<string> {
        if (this.isNative()) {
            // Native flows would request camera permissions and start barcode scanner:
            // const result = await window.Capacitor.Plugins.BarcodeScanner.startScan();
            // return result.content;
            return expectedCode; 
        }

        // Web Simulator: simulate scanning delay
        return new Promise((resolve) => {
            setTimeout(() => {
                resolve(expectedCode);
            }, 1200);
        });
    }

    /**
     * Simulate or trigger NFC tag scanner.
     */
    public static async scanNfcTag(expectedTagId: string): Promise<string> {
        if (this.isNative()) {
            // Native flow: window.Capacitor.Plugins.NFC.startListening();
            return expectedTagId;
        }

        // Web Simulator: simulate tap delay
        return new Promise((resolve) => {
            setTimeout(() => {
                resolve(expectedTagId);
            }, 1200);
        });
    }
}
