# ChangeLog

## Version 2.1

- Added handling of common Google referral URLs
- Added 'result_url' to the return structure to inform the caller what the resultant URL was after redirects


## Version 2.0.1

- Turned off debugging left on by mistake

## Version 2.0

- Added ability to manually set User-Agent, fixing many readability issues
- Updated redirect detection logic to more accurately read HTTP headers.
- Updated dependencies
  - Updated PHPUnit to ^8.0
  - Updated andreskrey/readability.php to ^2.1.0
- Updated PHP dependency to ^7.2

## Version 1.0.1

- Fixed minor issue with `parse_url` check.

## Version 1.0

- Updated to modify the approach for cleaning HTML tags and dealing with newlines.
- Updated README.md to outline the new text format.
- Closes issue #25

## Version 0.9

- Updated to include cleaning up of article text.

## Version 0.8.5

- Updated redirect checking logic to include ports


## Version 0.8.4

- Resolved 301 redirects to incomplete URL

## Version 0.8.3

- Closes issue #23 related to 301 redirects when scheme and host is not present.

## Version 0.8

- Added [andreskrey/readability.php](https://github.com/andreskrey/readability.php) library as the default method of article parsing, using prior methods as a backup.
  - This closes multiple issues related to article reading including #6, #7, #8, #17, #18
- Changed the call to parse a URL from `getArticleText to `processURL`
- Added README.md
- Upgraded to PHPUnit 6.x for testing


## Version 0.7

- Started CHANGELOG
- Moved PHP DOM Parser dependency to [thesoftwarefanatics/php-html-parser](https://github.com/thesoftwarefanatics/php-html-parser) for support of PHP 7.2+
