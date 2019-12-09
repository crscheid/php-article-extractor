# PHP Article extractor

This is a web article parsing and language detection library for PHP. This library reads the article content from a web page, removing all HTML and providing just the raw text, suitable for text to speech or machine learning processes.

For a project I have developed, I found many existing open source solutions good starting points, but each had unique failures. This library aggregates three different approaches into a single solution while adding the additional functionality of language detection.

## How To Use

This library is distributed via packagist.org, so you can use composer to retrieve the dependency

```
composer require crscheid/php-article-extractor
```

Then you need simply to create an ArticleExtractor class and call the `parseURL` function on it, passing in the URL desired.

```php
use Cscheide\ArticleExtractor\ArticleExtractor;

$extractor = new ArticleExtractor();

$response = $extractor->processURL("https://www.fastcompany.com/3067246/innovation-agents/the-unexpected-design-challenge-behind-slacks-new-threaded-conversations");
var_dump($response);
```

The function `processURL` returns an array containing the title, text, and meta data associated with the request. If the text is `null` then this indicates a failed parsing. Below should be the output of the above code.

```
array(5) {
  ["parse_method"]=>
  string(11) "readability"
  ["title"]=>
  string(72) "The Unexpected Design Challenge Behind Slack’s New Threaded Conversations"
  ["text"]=>
  string(8013) "At first blush, threaded conversations sound like one of the most thoroughly mundane features a messaging app could introduce.After all, the idea of neatly bundling up a specific message and its replies in ..."
  ["language_method"]=>
  string(7) "service"
  ["language"]=>
  string(2) "en"
}
```

You can also create the `ArticleExtractor` class by passing in a key for the language detection service. See more information below.


## Language Detection Methods

Language detection is handled by either looking for language specifiers within the HTML meta data or by utilizing the [Detect Language](http://detectlanguage.com/) service.

If it is possible to detect the language of the article, the language code in [ISO 639-1](http://www.loc.gov/standards/iso639-2/php/code_list.php) format as well as the detection method are returned in the fields `language` and `language_method` respectively. The `language_method` field, if found successfully, may be either `html` or `service`.

If language detection fails or is not available, both of these fields will be returned as null.

[Detect Language](http://detectlanguage.com/) requires the use of an API KEY which you can sign up for. However, you can also use this library without it. If the HTML meta data do not contain information about the language of the article, then `language` and `language_method` will be returned as null values.

To utilize this library utilizing the language detection service, create the `ArticleExtractor` object by passing in your API KEY for [Detect Language](http://detectlanguage.com/) or by setting `DETECT_LANGUAGE_KEY` in your environment variables.

```php
use Cscheide\ArticleExtractor\ArticleExtractor;

$extractor = new ArticleExtractor('your api key');
```


## Running tests

Unit tests are included in this distribution and can be run utilizing PHPUnit

```
./vendor/phpunit/phpunit/phpunit
```

Note: You may need to set the environment variable `DETECT_LANGUAGE_KEY` with your [Detect Language](http://detectlanguage.com/) key in order for language detection to work properly.
