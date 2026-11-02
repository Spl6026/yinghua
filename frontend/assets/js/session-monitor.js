import { initializeSiteInformation } from './core/site-information.js';
import { startSessionMonitoring } from './core/session-expiry.js';
startSessionMonitoring();

void initializeSiteInformation();
