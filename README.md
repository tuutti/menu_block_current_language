#Menu block current language
Menu block current language provides a replacement for core's Menu block that filters out the untranslated menu links.

See https://www.drupal.org/node/2466553 for more details.

##Usage
In order for this module to have any effect, you must replace menu blocks provided by the System module with menu blocks provided by this module.

Supported menu link types:
- Custom menu links 
- Views menu links 
- String translated  links 

Enabled menu link types can be configured on the "Configure block" page.

Custom menu links can expose their multilingual capabilities by implementing the `\Drupal\menu_block_current_language\MenuLinkTranslatableInterface` interface.
