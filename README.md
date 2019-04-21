# Attachments

MediaWiki extension to attach files and external links to pages.

* [subpages](https://www.mediawiki.org/wiki/Help:Subpages) are automatically attached to their parent page
* files can be attached to a page with `{{#attach: pagename}}`
* external links can be defined as subpages containing `{{#exturl: URL}}`

For enabled namespaces the attachments of an article are displayed in an automatically generated section at its end. To facilitate adding attachments a special page, `Special:Attach` is provided, which is linked as a page action in the Vector and Minerva skins.

## Tips

* `#attach` also works on regular articles.
* You can link files added through `Special:Attach` relatively with `[[File:{{FILEPREFIX}}filename.jpg]]`.
* Attachments and external URLs are both stored as [page props](https://www.mediawiki.org/wiki/Manual:Page_props_table), meaning they can be queried with [API:Pageprops](https://www.mediawiki.org/wiki/API:Pageprops) and [API:Pageswithprop](https://www.mediawiki.org/wiki/API:Pageswithprop).
* You can change how attachments are sorted with the `BeforeSortAttachments` hook.

## Setup

[Uploads need to be enabled](https://www.mediawiki.org/wiki/Manual:Configuring_file_uploads#Setting_uploads_on/off) and [subpages need to be enabled](https://www.mediawiki.org/wiki/Manual:LocalSettings.php#Enabling_subpages).

Then enable the extension for the desired namespaces, e.g:

	$wgAttachmentsNamespaces[NS_MAIN] = true;

## Credits

This extension is essentially a rewrite of PerPageResources by Mathias Ertl, which consists of [Extension:Resources](https://fs.fsinf.at/wiki/Resources), [Extension:AddResource](https://fs.fsinf.at/wiki/AddResource) and [Extension:ExternalRedirects](https://github.com/mathiasertl/ExternalRedirects). This extension replaces all three, notable differences are:

* attachments are stored in page\_props instead of pagelinks
* no open redirects, just links
* attachments are shown at the end of pages, as opposed to on a special page

`resources/folder-plus.svg` is from [IcoMoon-Free](https://github.com/Keyamoon/IcoMoon-Free) and licensed under [CC BY 4.0](http://creativecommons.org/licenses/by/4.0/). The vector was minified and colored.

## Planned features

* `Special:ActuallyUnusedFiles` to list files which are both unused and unattached.
* Magic words for statistics: `{{NUMBEROFATTACHMENTS}}` and `{{ATTACHMENTSFORNS:index}}`
* `{{#attachments hide subpages: prefix}}` for use on content pages to hide subpages starting with the given prefix from the autoindex.
