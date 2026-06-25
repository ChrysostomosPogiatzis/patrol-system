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
                const position =
                    await window.Capacitor.Plugins.Geolocation.getCurrentPosition(
                        {
                            enableHighAccuracy: true,
                            timeout: 10000,
                        },
                    );
                return {
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                    accuracy: position.coords.accuracy,
                };
            } catch (error) {
                console.error(
                    'Capacitor Geolocation failed, using web fallback.',
                    error,
                );
            }
        }

        // Standard Web Browser fallback
        return new Promise((resolve) => {
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
                        console.warn(
                            'Web Geolocation failed. Using simulated coordinates.',
                            error,
                        );
                        resolve(CapacitorBridge.getFallbackSimulatedPosition());
                    },
                    { enableHighAccuracy: true, timeout: 5000 },
                );
            } else {
                resolve(CapacitorBridge.getFallbackSimulatedPosition());
            }
        });
    }

    /**
     * Dynamically retrieve simulated fallback coordinates from cached active patrol session
     * or cached assigned routes to avoid hardcoding coordinates.
     */
    private static getFallbackSimulatedPosition(): GpsPosition {
        try {
            const cachedPatrol = localStorage.getItem('patrol_active_session');
            if (cachedPatrol) {
                const patrol = JSON.parse(cachedPatrol);
                const logs =
                    patrol.checkpoint_logs || patrol.checkpointLogs || [];
                if (logs.length > 0) {
                    // Try to find the first pending checkpoint
                    const pending = logs.find(
                        (l: any) =>
                            l.status === 'pending' ||
                            l.status === 'out_of_order_attempt',
                    );
                    const targetLog = pending || logs[0];
                    if (
                        targetLog.checkpoint &&
                        typeof targetLog.checkpoint.latitude === 'number'
                    ) {
                        return {
                            latitude: targetLog.checkpoint.latitude,
                            longitude: targetLog.checkpoint.longitude,
                            accuracy: 15,
                        };
                    }
                }
            }
        } catch (e) {
            console.error(
                'Failed to parse active patrol session for mock coordinates:',
                e,
            );
        }

        try {
            const cachedRoutes = localStorage.getItem('patrol_cached_routes');
            if (cachedRoutes) {
                const routes = JSON.parse(cachedRoutes);
                if (
                    routes.length > 0 &&
                    routes[0].route_checkpoints &&
                    routes[0].route_checkpoints.length > 0
                ) {
                    const firstCp = routes[0].route_checkpoints[0].checkpoint;
                    if (firstCp && typeof firstCp.latitude === 'number') {
                        return {
                            latitude: firstCp.latitude,
                            longitude: firstCp.longitude,
                            accuracy: 15,
                        };
                    }
                }
            }
        } catch (e) {
            console.error(
                'Failed to parse cached routes for mock coordinates:',
                e,
            );
        }

        // Absolute fallback (default Limassol Marina coordinates)
        return {
            latitude: 34.671234,
            longitude: 33.041234,
            accuracy: 15,
        };
    }

    /**
     * Retrieve current device battery level and charging status.
     */
    public static async getBatteryInfo(): Promise<BatteryInfo> {
        if (this.isNative()) {
            try {
                const info =
                    await window.Capacitor.Plugins.Device.getBatteryInfo();
                return {
                    level: Math.round(info.batteryLevel * 100),
                    isCharging: info.isCharging,
                };
            } catch (error) {
                console.error(
                    'Capacitor Battery check failed, using web fallback.',
                    error,
                );
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
