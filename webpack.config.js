const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')

webpackConfig.entry = {
	'admin-settings': path.join(__dirname, 'src', 'main.js'),
	'personal-settings': path.join(__dirname, 'src', 'personal-main.js'),
}

// Disable source maps in production to avoid 404s on deploy
if (process.env.NODE_ENV === 'production') {
	webpackConfig.devtool = false
}

module.exports = webpackConfig
