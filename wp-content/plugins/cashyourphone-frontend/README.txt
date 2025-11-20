CashYourPhone Combined Plugin

This plugin contains the React build (in /build) and the backend WordPress PHP
file (cashyourphone-combined.php) that registers CPTs and REST endpoints.

Installation:
1. Upload the zip to WordPress Admin -> Plugins -> Add New -> Upload Plugin.
2. Activate the plugin.
3. Go to Settings -> Permalinks and click Save Changes.

API Endpoints:
- GET /wp-json/wp/v2/cyf_device
- GET /wp-json/wp/v2/cyf_review
- POST /wp-json/wp/v2/cyf_review
