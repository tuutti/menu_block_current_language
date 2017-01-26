#Menu block current language
Menu block current language provides a replacement for
core's Menu block that filters out the untranslated menu links.

See https://www.drupal.org/node/2466553 for more details.

##Usage
In order for this module to have any effect, you must replace menu blocks
provided by the System module with menu blocks provided by this module.

Supported menu link types:
- Custom menu links
- Views menu links
- String translated links

Enabled menu link types can be configured on the "Configure block" page.

Custom menu links can expose their multilingual capabilities by:
 - Implementing the
 `\Drupal\menu_block_current_language\MenuLinkTranslatableInterface` interface.
 - Responding to
 `\Drupal\menu_block_current_language\Event\Events::HAS_TRANSLATION` event.

##Installation with composer
Add the following code to your composer.json file under the "repositories" key:

```
{
  "type": "git",
  "url": "tuutti@git.drupal.org:sandbox/tuutti/2832329.git"
}
```

and execute `composer require drupal/menu_block_current_language`.

##Similar modules

**[Menu Multilingual](https://www.drupal.org/sandbox/matsbla/2831709)**

Works without replacing menu blocks, but has fewer features.
