# Simple FTP CDN Uploader

THIS README IS WRITTEN USING CLAUDE AI AND MAY CONTAIN ERRORS

A lightweight WordPress plugin that automatically uploads media files to your CDN via FTP and serves them from there.

* Plugin Name: Simple FTP CDN Uploader
* Description: Upload media files to CDN via FTP with admin configuration
* Version: 1.0
* Author: Alex Kovalev
* Author URI: https://github.com/alexKov24/
* License: GPL2

## Features

* Auto-upload media files to CDN via FTP
* Serve media from CDN URLs
* Support for custom file types (default: mp4)
* Optional local file cleanup
* Media library integration with CDN status
* Real-time FTP connection testing

## Installation

1. Clone this repository or download the zip
2. Upload to `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Configure FTP settings in Settings -> FTP CDN

## Configuration

Navigate to Settings -> FTP CDN and configure:

* **FTP Server** - Your FTP server address
* **FTP Username** - Your FTP credentials
* **FTP Password** - Your FTP credentials
* **CDN URL** - Base URL of your CDN
* **CDN Base Path** - Base directory on CDN server
* **CDN Prefix** - Optional URL prefix
* **File Types** - Which files to process (default: mp4)
* **Delete Local** - Option to remove local files after upload

## Usage

The plugin works automatically once configured:

1. Upload media file through WordPress
2. Plugin detects file type and uploads to CDN if matched
3. Media URLs are rewritten to serve from CDN
4. Upload status shown in media library

## Requirements

* WordPress 5.0 or higher
* PHP 7.0 or higher
* FTP access to CDN

## Changelog

### 1.0.0
* Initial release
* FTP upload functionality
* Media library integration
* Settings management
* Connection testing

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is licensed under GPL2 - see the [LICENSE](LICENSE) file for details