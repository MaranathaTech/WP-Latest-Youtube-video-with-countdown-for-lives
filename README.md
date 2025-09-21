# YouTube Latest Video Player

A WordPress plugin that allows users to enter their YouTube channel streams URL and displays a video player that dynamically loads the latest video from their channel.

## Features

- **Easy Setup**: Simple admin interface to configure your YouTube channel
- **Automatic Updates**: Fetches the latest video automatically with caching
- **Responsive Design**: Mobile-friendly video player that adapts to different screen sizes
- **Shortcode Support**: Easy integration into posts, pages, and widgets
- **Customizable**: Configurable player dimensions and autoplay settings
- **Performance Optimized**: Built-in caching system to reduce API calls
- **YouTube Data API v3**: Uses official YouTube API for reliable data fetching

## Installation

### Method 1: Manual Installation

1. Download the plugin files
2. Upload the `youtube-latest-video-player` folder to your `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Go to **Settings > YouTube Video Player** to configure the plugin

### Method 2: WordPress Admin Upload

1. In your WordPress admin, go to **Plugins > Add New**
2. Click **Upload Plugin**
3. Choose the plugin ZIP file and click **Install Now**
4. Activate the plugin

## Configuration

### Step 1: Get a YouTube Data API Key

1. Go to the [Google Developers Console](https://console.developers.google.com/)
2. Create a new project or select an existing one
3. Enable the **YouTube Data API v3**
4. Create credentials (API key)
5. Copy the API key

### Step 2: Configure the Plugin

1. In WordPress admin, go to **Settings > YouTube Video Player**
2. Enter your **YouTube Channel Streams URL** (e.g., `https://www.youtube.com/@templechurchpca2042/streams`)
3. Enter your **YouTube Data API Key**
4. Set the **Cache Duration** (how long to cache video data, default: 5 minutes)
5. Click **Save Changes**

## Usage

### Basic Shortcode

Display your latest video with default settings:

```
[youtube_latest_video]
```

### Shortcode with Parameters

Customize the video player:

```
[youtube_latest_video width="800" height="450" autoplay="1"]
```

### Available Parameters

- `width` - Video player width in pixels (default: 560)
- `height` - Video player height in pixels (default: 315)
- `autoplay` - Auto-play the video (0 = no, 1 = yes, default: 0)

### Examples

**Large player with autoplay:**
```
[youtube_latest_video width="800" height="450" autoplay="1"]
```

**Small player:**
```
[youtube_latest_video width="400" height="225"]
```

**Mobile-optimized (responsive):**
```
[youtube_latest_video width="100%" height="auto"]
```

## Supported YouTube URL Formats

The plugin supports various YouTube channel URL formats:

- `https://www.youtube.com/@channelname/streams`
- `https://www.youtube.com/channel/UC1234567890/streams`
- `https://www.youtube.com/c/channelname/streams`

## Caching

The plugin includes an intelligent caching system:

- **Default Cache Duration**: 5 minutes (300 seconds)
- **Configurable**: Can be set from 1 minute to 1 hour
- **Automatic Refresh**: Cache automatically refreshes when expired
- **Performance**: Reduces API calls and improves page load times

## Troubleshooting

### Video Not Loading

1. **Check API Key**: Ensure your YouTube Data API key is valid and has the YouTube Data API v3 enabled
2. **Verify Channel URL**: Make sure the YouTube channel URL is correct and accessible
3. **API Quota**: Check if you've exceeded your YouTube API quota (default: 10,000 units/day)
4. **Cache Issues**: Try clearing the cache by temporarily changing the cache duration

### Common Issues

**"Unable to load latest video" Error:**
- Verify your API key is correct
- Check that the YouTube Data API v3 is enabled in Google Console
- Ensure the channel URL is publicly accessible
- Check WordPress error logs for detailed error messages

**Player Not Responsive:**
- Clear browser cache
- Check for CSS conflicts with your theme
- Ensure the shortcode is placed in a container with proper width

**API Quota Exceeded:**
- Increase cache duration to reduce API calls
- Consider upgrading your Google Cloud quota if needed
- Monitor your API usage in Google Console

## Technical Requirements

- **WordPress**: 4.0 or higher
- **PHP**: 7.0 or higher
- **YouTube Data API v3**: Valid API key required
- **Internet Connection**: Required for fetching video data

## File Structure

```
youtube-latest-video-player/
├── youtube-latest-video-player.php    # Main plugin file
├── assets/
│   ├── style.css                      # Frontend styles
│   └── script.js                      # Frontend JavaScript
└── README.md                          # Documentation
```

## Hooks and Filters

### Available Filters

- `ylvp_video_data` - Filter video data before display
- `ylvp_shortcode_atts` - Filter shortcode attributes
- `ylvp_cache_duration` - Filter cache duration

### Example Usage

```php
// Modify video data before display
add_filter('ylvp_video_data', function($video_data) {
    // Custom modifications
    return $video_data;
});

// Change default cache duration
add_filter('ylvp_cache_duration', function($duration) {
    return 600; // 10 minutes
});
```

## Privacy and Data

This plugin:
- Fetches public video data from YouTube
- Does not collect personal user data
- Stores YouTube API responses temporarily in WordPress transients
- Uses secure HTTPS connections for all API requests

## Support

For support and bug reports:
1. Check the troubleshooting section above
2. Review WordPress error logs
3. Test with a default WordPress theme to rule out conflicts
4. Create an issue on the plugin repository

## License

This plugin is licensed under the GPL v2 or later.

## Changelog

### Version 1.0.0
- Initial release
- YouTube Data API v3 integration
- Responsive video player
- Caching system
- Admin configuration interface
- Shortcode support with parameters