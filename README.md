# Attachments

MediaWiki extension to attach files and external links to pages.

* [subpages](https://www.mediawiki.org/wiki/Help:Subpages) are automatically attached to their parent page
* files can be attached to a page with `{{#attach: pagename}}`
* external links can be defined as subpages containing `{{#exturl: URL}}`

For enabled namespaces the attachments of an article are displayed in an automatically generated section at its end. To facilitate adding attachments an `attach` page action is provided, which is linked in the Vector and Minerva skins.

## Tips

* `#attach` also works on regular articles.
* You can link files added through `action=attach` relatively with `[[File:{{FILEPREFIX}}filename.jpg]]` (or `{{FILEPREFIX:..}}` for the parent page).
* Enable [$wgCountCategorizedImagesAsUsed](https://www.mediawiki.org/wiki/Manual:$wgCountCategorizedImagesAsUsed) to exclude attached files from `Special:UnusedImages`.
* You can exclude subpages starting with a specific prefix from the autoindex with `{{#attachments ignore subpages: prefix}}` on the parent page.
* Attachments and external URLs are both stored as [page props](https://www.mediawiki.org/wiki/Manual:Page_props_table), meaning they can be queried with [API:Pageprops](https://www.mediawiki.org/wiki/API:Pageprops) and [API:Pageswithprop](https://www.mediawiki.org/wiki/API:Pageswithprop).
* You can access attachments before they are sorted with the `BeforeSortAttachments(&$links)` hook, where links is an associative array mapping string keys to HTML links. Return false to take over the sorting.
* If new attachments do not show up, it might be because you have many jobs in your [job queue](https://www.mediawiki.org/wiki/Manual:Job_queue).

## Setup

[Enable uploads](https://www.mediawiki.org/wiki/Manual:Configuring_file_uploads#Setting_uploads_on/off).

Place the extension in your extensions directory and load it with `wfLoadExtension('Attachments');`.

Then enable the extension for the desired namespaces, e.g:

	$wgAttachmentsNamespaces[NS_MAIN] = true;

Note that you should also [enable subpages](https://www.mediawiki.org/wiki/Manual:LocalSettings.php#Enabling_subpages) for these namespaces.

## Optional configuration parameters

* `$wgAttachmentsChunkListByLetter`  
  Boolean, whether or not the attachment list should be chunked by the first
  letter of list items. Defaults to `true`.
* `$wgAttachmentsShowSubpageForm`  
  Boolean, whether or not the subpage form should be shown. Defaults to `true`.
* `$wgAttachmentsShowLinkForm`  
  Boolean, whether or not the external link form should be shown. Defaults to `true`.

## Credits

This extension is essentially a rewrite of PerPageResources by Mathias Ertl, which consists of [Extension:Resources](https://fs.fsinf.at/wiki/Resources), [Extension:AddResource](https://fs.fsinf.at/wiki/AddResource) and [Extension:ExternalRedirects](https://github.com/mathiasertl/ExternalRedirects). This extension replaces all three, notable differences are:

* attachments are stored in page\_props instead of pagelinks
* no open redirects, just links
* attachments are shown at the end of pages, as opposed to on a special page

The icons in `resources/` are from [Feather](https://feathericons.com/) and licensed under MIT.
