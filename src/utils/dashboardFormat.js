export function isObject(v) {
	return v !== null && typeof v === 'object' && !Array.isArray(v)
}

export function isBytesMetric(metricId) {
	if (!metricId) return false
	const s = String(metricId).toLowerCase()
	return s.includes('bytes') || s.includes('storage') || s.includes('disk_space') || s.includes('db_size')
}

export function formatBytes(bytes) {
	if (bytes === null || bytes === undefined) return '–'
	if (bytes === 0) return '0 B'
	const units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB']
	const i = Math.floor(Math.log(Math.abs(bytes)) / Math.log(1024))
	const val = bytes / Math.pow(1024, i)
	return val.toFixed(i > 0 ? 1 : 0) + ' ' + units[i]
}

export function slugify(s) {
	return String(s || 'dashboard')
		.toLowerCase()
		.replace(/[^a-z0-9]+/g, '-')
		.replace(/^-+|-+$/g, '')
		.slice(0, 60) || 'dashboard'
}

export function formatCollectorName(id) {
	const overrides = {
		richdocuments: 'Office documents',
		talk: 'Talk',
		deck: 'Deck',
		system: 'System',
	}
	if (overrides[id]) return overrides[id]
	return id.charAt(0).toUpperCase() + id.slice(1).replace(/_/g, ' ')
}

const METRIC_NAME_OVERRIDES = {
	installed_apps: 'Installed apps',
	mimetypes_distribution: 'File types',
	nc_version: 'Nextcloud version',
	php_version: 'PHP version',
	db_type: 'Database engine',
	db_size_bytes: 'Database size',
	free_disk_space: 'Free disk space',
	total_storage_bytes: 'Total storage',
	avg_storage_per_user: 'Avg storage per user',
	largest_file_bytes: 'Largest file',
	files_added_24h: 'Files added (24h)',
	files_added_7d: 'Files added (7d)',
	total_users: 'Total users',
	active_users_24h: 'Active users (24h)',
	active_users_7d: 'Active users (7d)',
	disabled_users: 'Disabled users',
	total_files: 'Total files',
	total_shares: 'Total shares',
	total_rooms: 'Talk rooms',
	total_cards: 'Deck cards',
	total_messages: 'Mail messages',
	total_events: 'Calendar events',
	total_contacts: 'Contacts',
	total_forms: 'Forms',
	total_activities: 'Activity entries',
	active_sessions: 'Active document sessions',
}

export function formatMetricName(id) {
	if (METRIC_NAME_OVERRIDES[id]) return METRIC_NAME_OVERRIDES[id]
	return String(id)
		.replace(/_/g, ' ')
		.replace(/\b\w/g, c => c.toUpperCase())
		.replace(/\bBytes\b/, '')
		.replace(/\b24h\b/i, '24h')
		.replace(/\b7d\b/i, '7d')
		.replace(/\b30d\b/i, '30d')
		.replace(/\bNc\b/i, 'NC')
		.replace(/\bPhp\b/i, 'PHP')
		.replace(/\bDb\b/i, 'DB')
		.replace(/\bUrl\b/i, 'URL')
		.trim()
}

const APP_FRIENDLY_NAMES = {
	activity: 'Activity',
	admin_audit: 'Admin audit log',
	bruteforcesettings: 'Brute-force settings',
	calendar: 'Calendar',
	circles: 'Circles',
	cloud_federation_api: 'Cloud federation',
	comments: 'Comments',
	contactsinteraction: 'Contacts interaction',
	contacts: 'Contacts',
	dashboard: 'Dashboard',
	dav: 'CalDAV / CardDAV',
	deck: 'Deck',
	encryption: 'Encryption',
	federatedfilesharing: 'Federated sharing',
	federation: 'Federation',
	files: 'Files',
	files_external: 'External storage',
	files_pdfviewer: 'PDF viewer',
	files_reminders: 'File reminders',
	files_sharing: 'File sharing',
	files_trashbin: 'Deleted files',
	files_versions: 'File versions',
	files_videoplayer: 'Video player',
	firstrunwizard: 'First-run wizard',
	forms: 'Forms',
	logreader: 'Log reader',
	lookup_server_connector: 'Lookup server connector',
	mail: 'Mail',
	meetingnotes: 'Meeting Notes',
	nextcloud_announcements: 'Nextcloud announcements',
	notes: 'Notes',
	notifications: 'Notifications',
	oauth2: 'OAuth 2.0',
	password_policy: 'Password policy',
	photos: 'Photos',
	privacy: 'Privacy',
	provisioning_api: 'Provisioning API',
	recommendations: 'Recommendations',
	related_resources: 'Related resources',
	richdocuments: 'Office (Collabora)',
	richdocumentscode: 'Built-in CODE server',
	serverinfo: 'Server info',
	settings: 'Settings',
	sharebymail: 'Share by mail',
	spreed: 'Talk',
	stats_collector: 'Stats Collector',
	support: 'Support',
	survey_client: 'Survey client',
	systemtags: 'System tags',
	text: 'Text editor',
	theming: 'Theming',
	twofactor_backupcodes: 'Two-factor backup codes',
	updatenotification: 'Update notifications',
	user_status: 'User status',
	viewer: 'File viewer',
	weather_status: 'Weather status',
	workflowengine: 'Workflow engine',
}

const MIMETYPE_FRIENDLY_NAMES = {
	'image/jpeg': 'JPEG image',
	'image/png': 'PNG image',
	'image/gif': 'GIF image',
	'image/webp': 'WebP image',
	'image/svg+xml': 'SVG image',
	'image/heic': 'HEIC image',
	'image/heif': 'HEIF image',
	'image/tiff': 'TIFF image',
	'image/bmp': 'Bitmap image',
	'image/x-icon': 'Icon',
	'application/pdf': 'PDF document',
	'application/msword': 'Word document (.doc)',
	'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'Word document (.docx)',
	'application/vnd.ms-excel': 'Excel sheet (.xls)',
	'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'Excel sheet (.xlsx)',
	'application/vnd.ms-powerpoint': 'PowerPoint (.ppt)',
	'application/vnd.openxmlformats-officedocument.presentationml.presentation': 'PowerPoint (.pptx)',
	'application/vnd.oasis.opendocument.text': 'OpenDocument text',
	'application/vnd.oasis.opendocument.spreadsheet': 'OpenDocument sheet',
	'application/vnd.oasis.opendocument.presentation': 'OpenDocument slides',
	'application/zip': 'ZIP archive',
	'application/x-zip-compressed': 'ZIP archive',
	'application/x-tar': 'TAR archive',
	'application/gzip': 'Gzip archive',
	'application/x-7z-compressed': '7-Zip archive',
	'application/x-rar-compressed': 'RAR archive',
	'application/x-rar': 'RAR archive',
	'application/json': 'JSON',
	'application/xml': 'XML',
	'application/javascript': 'JavaScript',
	'application/x-php': 'PHP source',
	'application/x-sh': 'Shell script',
	'application/octet-stream': 'Binary file',
	'application/epub+zip': 'EPUB book',
	'audio/mpeg': 'MP3 audio',
	'audio/mp4': 'MP4 audio',
	'audio/ogg': 'OGG audio',
	'audio/wav': 'WAV audio',
	'audio/x-wav': 'WAV audio',
	'audio/flac': 'FLAC audio',
	'video/mp4': 'MP4 video',
	'video/quicktime': 'QuickTime video',
	'video/x-matroska': 'Matroska video (.mkv)',
	'video/webm': 'WebM video',
	'video/x-msvideo': 'AVI video',
	'video/mpeg': 'MPEG video',
	'text/plain': 'Plain text',
	'text/markdown': 'Markdown',
	'text/csv': 'CSV',
	'text/html': 'HTML',
	'text/css': 'CSS',
	'text/calendar': 'Calendar (.ics)',
	'text/vcard': 'vCard',
	'httpd/unix-directory': 'Folder',
}

function humanizeMimeType(mime) {
	if (MIMETYPE_FRIENDLY_NAMES[mime]) return MIMETYPE_FRIENDLY_NAMES[mime]
	if (typeof mime !== 'string' || !mime.includes('/')) return mime
	const [top, sub] = mime.split('/')
	const subClean = sub.replace(/^x-/, '').replace(/^vnd\.[^.]*\./, '').replace(/[._-]/g, ' ')
	const topMap = { image: 'image', audio: 'audio', video: 'video', text: 'text', application: 'file' }
	const topLabel = topMap[top.toLowerCase()] || top
	return `${subClean.charAt(0).toUpperCase() + subClean.slice(1)} ${topLabel}`
}

// Friendly name for an entry key inside a "map" metric (e.g. installed_apps, mimetypes_distribution).
// Falls back to a humanized version of the raw key.
export function formatMapKey(metricId, key) {
	if (!key) return ''
	if (metricId === 'installed_apps') {
		return APP_FRIENDLY_NAMES[key] || formatCollectorName(key)
	}
	if (metricId === 'mimetypes_distribution') {
		return humanizeMimeType(key)
	}
	return String(key)
}

export function formatTimestamp(ts) {
	if (!ts) return ''
	try {
		return new Date(ts).toLocaleString(undefined, {
			dateStyle: 'medium',
			timeStyle: 'short',
		})
	} catch (e) {
		return ts
	}
}

export function formatValue(value, metricId) {
	if (value === null || value === undefined) return '–'
	if (typeof value === 'boolean') return value ? 'Yes' : 'No'
	if (Array.isArray(value)) return value.length.toLocaleString() + ' items'
	if (typeof value === 'number') {
		if (isBytesMetric(metricId)) return formatBytes(value)
		return value.toLocaleString()
	}
	return String(value)
}

export function formatSimpleValue(value, metricId) {
	return formatValue(value, metricId)
}

export function metricLabel(key) {
	if (!key) return ''
	const [collectorId, metricId] = String(key).split('.')
	if (!collectorId || !metricId) return key
	return formatCollectorName(collectorId) + ' · ' + formatMetricName(metricId)
}
