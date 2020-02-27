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

The field `result_url` will be different if the library followed redirects. This field represents the final page actually retrieved after redirects.

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
  ["result_url"]=>
  string(126) "https://www.fastcompany.com/3067246/innovation-agents/the-unexpected-design-challenge-behind-slacks-new-threaded-conversations"

}
```

You can also create the `ArticleExtractor` class by passing in a key for the language detection service as well as a custom User-Agent string. See more information below.


## Options

### Language Detection Methods

Language detection is handled by either looking for language specifiers within the HTML meta data or by utilizing the [Detect Language](http://detectlanguage.com/) service.

If it is possible to detect the language of the article, the language code in [ISO 639-1](http://www.loc.gov/standards/iso639-2/php/code_list.php) format as well as the detection method are returned in the fields `language` and `language_method` respectively. The `language_method` field, if found successfully, may be either `html` or `service`.

If language detection fails or is not available, both of these fields will be returned as null.

[Detect Language](http://detectlanguage.com/) requires the use of an API KEY which you can sign up for. However, you can also use this library without it. If the HTML meta data do not contain information about the language of the article, then `language` and `language_method` will be returned as null values.

To utilize this library utilizing the language detection service, create the `ArticleExtractor` object by passing in your API KEY for [Detect Language](http://detectlanguage.com/).

```php
use Cscheide\ArticleExtractor\ArticleExtractor;

$extractor = new ArticleExtractor('your api key');
```

### Setting User Agent

It is possible to set the user-agent for outgoing requests. To do so pass the desired user agent string to the constructor as follows:

```php
use Cscheide\ArticleExtractor\ArticleExtractor;

$myUserAgent = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.116 Safari/537.36";
$extractor = new ArticleExtractor(null, $myUserAgent);
```


## Output Format

As of version 1.0, the output format has been altered to provide newline breaks for headings. This is important especially for natural language processing applications in determining sentence boundaries. If this behavior is not desired, simply strip out the additional newlines where needed.

This change was made due the fact that when header and paragraph HTML elements are simply stripped out, there often occurs issues where there is no separation between the heading and the proceeding sentence.

**Example of Output Format for Text Field**

```
\n
A database containing 250 million Microsoft customer records has been found unsecured and online\n
NurPhoto via Getty Images\n
A new report reveals that 250 million Microsoft customer records, spanning 14 years, have been exposed online without password protection.\n
Microsoft has been in the news for, mostly, the wrong reasons recently. There is the Internet Explorer zero-day vulnerability that Microsoft hasn't issued a patch for, despite it being actively exploited. That came just days after the U.S. Government issued a critical Windows 10 update now alert concerning the "extraordinarily serious" curveball crypto vulnerability. Now a newly published report, has revealed that 250 million Microsoft customer records, spanning an incredible 14 years in all, have been exposed online in a database with no password protection.\n
What Microsoft customer records were exposed online, and where did they come from?\n
```


## Running tests

Unit tests are included in this distribution and can be run utilizing PHPUnit

```
./vendor/phpunit/phpunit/phpunit
```

> Note: Please set the environment variable `DETECT_LANGUAGE_KEY` with your [Detect Language](http://detectlanguage.com/) key in order for language detection to work properly.
