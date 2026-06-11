import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'com.patrolsystem.app',
  appName: 'Patrol System',
  webDir: 'public',
  server: {
    url: 'https://patrol.witbo.com.cy/guard',
    cleartext: true
  }
};

export default config;
