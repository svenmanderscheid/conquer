'use strict';
const fs = require('node:fs');
const path = require('node:path');

const packages = {debug: 'com.unionofkingdoms.app.prototype', release: 'com.unionofkingdoms.app'};

// Match Google Services' variant override before the app-level fallback.
// Return only a diagnostic, never credentials or the complete Firebase file.
function inspectFirebaseConfig(android, variant = 'release') {
  if (!Object.hasOwn(packages, variant)) throw new Error('Unknown Android build variant.');
  const packageName = packages[variant];
  const candidates = [path.join(android, 'app/src', variant, 'google-services.json'), path.join(android, 'app/google-services.json')];
  const file = candidates.find(candidate => fs.existsSync(candidate));
  const result = {ready: false, variant, packageName, path: file || candidates[0]};
  if (!file) return {...result, reason: `Add Firebase Android configuration for ${packageName} at android/app/src/${variant}/google-services.json.`};
  let config;
  try { config = JSON.parse(fs.readFileSync(file, 'utf8')); }
  catch { return {...result, reason: `Firebase ${variant} configuration is not valid JSON.`}; }
  const client = Array.isArray(config?.client) ? config.client.find(value => value?.client_info?.android_client_info?.package_name === packageName) : null;
  if (!client) return {...result, reason: `Firebase ${variant} configuration has no client for ${packageName}. Download the matching Android app configuration.`};
  if (!/^\d+$/.test(String(config?.project_info?.project_number || '')) ||
      typeof config?.project_info?.project_id !== 'string' || !config.project_info.project_id.trim() ||
      typeof client.client_info.mobilesdk_app_id !== 'string' || !client.client_info.mobilesdk_app_id.includes(':android:') ||
      !Array.isArray(client.api_key) || !client.api_key.some(value => typeof value?.current_key === 'string' && value.current_key.trim())) {
    return {...result, reason: `Firebase ${variant} configuration is incomplete. Download google-services.json from the Firebase project.`};
  }
  return {...result, ready: true, reason: ''};
}

function requireFirebaseConfig(android, variant = 'release') {
  const result = inspectFirebaseConfig(android, variant);
  if (!result.ready) throw new Error(result.reason);
  return result;
}

module.exports = {inspectFirebaseConfig, requireFirebaseConfig};
