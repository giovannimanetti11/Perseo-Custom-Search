# Perseo Custom Search for WordPress

## Description

Perseo Custom Search is a minimalistic and open-source WordPress plugin designed to customize the search experience on WordPress websites. This plugin allows users to perform searches by filtering categories, tags, and specific keywords. A distinctive feature of Perseo Custom Search is its integration with the "perseo-catandtags" plugin, which enables the display of images for categories and tags in the search form, making the search process not only intuitive but also visually appealing.

In Perseo Custom Search the keyword search is conducted within a specific paragraph of articles, identified by a unique ID. This feature allows for more targeted and relevant search results, focusing on the specific content you want to search in.

For full functionality, ensure the "perseo-catandtags" plugin is installed and activated. Find it here: [perseo-catandtags](https://github.com/giovannimanetti11/perseo-catandtags).


## Installation

1. Clone or download the zip file of the plugin from this GitHub repository.
2. Go to your WordPress admin dashboard, navigate to 'Plugins > Add New > Upload Plugin', and upload the zip file.
3. Activate the plugin through the 'Plugins' menu in WordPress.
4. Use the shortcode `[perseo_custom_search]` in any post or page to display the custom search form.

### Customization

In the current release, categories and the ID of the paragraph where keywords are searched must be configured directly within the plugin's backend code, as there is no feature available yet to configure them from the WordPress admin panel. Adjusting these settings requires manual modifications to the plugin's files.

If you need to customize categories, the specific paragraph ID for keyword searches, or any other plugin settings, and are not comfortable making these changes in the code, please do not hesitate to reach out for support. I am available to assist with custom modifications to ensure the plugin meets your specific needs.

## Usage

After activation, simply insert the shortcode `[perseo_custom_search]` wherever you want the custom search form to appear on your site. The plugin handles the rest, from user input to displaying the search results.

## Development

This plugin is actively developed by Giovanni Manetti, with the source code available on GitHub. Contributions, bug reports, and feature requests are welcome.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Author

- Giovanni Manetti - [GitHub](https://github.com/giovannimanetti11)
