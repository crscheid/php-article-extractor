<?php

namespace Cscheide\ArticleExtractor;

use Goose\Client as GooseClient;

use GuzzleHttp\Client as GuzzleClient;

use andreskrey\Readability\Readability;
use andreskrey\Readability\Configuration;
use andreskrey\Readability\ParseException;

use PHPHtmlParser\Dom;
use PHPHtmlParser\Options;
use PHPHtmlParser\Dom\Node\HtmlNode;
use PHPHtmlParser\Dom\Node\TextNode;

use DetectLanguage\DetectLanguage;

class ArticleExtractor {

	// Debug flag - set to true for convenience during development
	private $debug = false;

	// Valid root elements we want to search for
	private $valid_root_elements = [ 'body', 'form', 'main', 'div', 'ul', 'li', 'table', 'span', 'section', 'article', 'main'];

	// Elements we want to place a space in front of when converting to text
	private $space_elements = ['p', 'li'];

	// API key for the remote detection service
	private $api_key = null;

	// User agent to override
	private $user_agent = null;

	// Method to force
	private $force_method = null;

	/**
	 * Constructor provides API key, user_agent, and the force method if required.
	 */
	public function __construct($api_key = null, $user_agent = null, $force_method = null) {
		$this->api_key = $api_key;
		$this->user_agent = $user_agent;

		if (!in_array($force_method, ['readability','goose','goosecustom','custom'])) {
			$this->force_method = null;
		}
		else {
			$this->force_method = $force_method;
		}
	}


  /**
   * Provided for backward compatibility.
   */
  public function getArticleText($url) {
    return $this->processURL($url);
  }

  /**
   *
   * This function returns the best guess of the human readable part of HTML that
   * is passed in as well as additional meta data associated with the parsing.
	 *
	 * Returns an array with the following information:
	 *
	 * [
	 *	  title => (the title of the article)
	 *	  text => (the human readable piece of the article)
	 *	  parse_method => (the internal processing method used to parse the article, "goose", "custom", "readability"
	 *	  language => (the ISO 639-1 code detected for the language)
	 *	  language_method => (the way the language was detected)
	 * ]
   *
   */
  public function processHTML($html) {

    // If we don't have a force method enabled, then simply run them in the following order
		if ($this->force_method == null) {

			// First try with readability
			$results = $this->parseHTMLViaReadability($html);

			// If we don't see what we want, try our other method
	    if ($results['text'] == null) {
	      $results = $this->parseHTMLViaGoose($html);
	    }

			// If we still don't have text, then try our custom method passing in the results from the prior Goose Call
			if ($results['text'] == null) {
	      $results = $this->parseHTMLViaCustom($html, $results);
	    }
		}
		// Otherwise, run them specifically in this order
		else {
			switch($this->force_method) {
				case 'readability':
				  $results = $this->parseHTMLViaReadability($html);
					break;
				case 'goose':
					$results = $this->parseHTMLViaGoose($html);
					break;
				case 'goosecustom':
					$results = $this->parseHTMLViaGoose($html);
					if ($results['text'] == null) {
			      $results = $this->parseHTMLViaCustom($html, $results);
			    }
					break;
				case 'custom':
					$results = $this->parseHTMLViaCustom($html);
					break;
			}
		}

		// Perform the post processing cleanup
    return $this->performHTMLPostProcessing($results);

  }

	/**
	 * This function returns the best guess of the human readable part of a URL,
   * as well as additional meta data associated with the parsing.
	 *
	 * Returns an array with the following information:
	 *
	 * [
	 *	  title => (the title of the article)
	 *	  text => (the human readable piece of the article)
	 *	  parse_method => (the internal processing method used to parse the article, "goose", "custom", "readability"
	 *	  language => (the ISO 639-1 code detected for the language)
	 *	  language_method => (the way the language was detected)
   *    result_url => (the resultant URL after all appropriate redirects have occurred)
	 * ]
   *
   * This processing will attempt to use the following methods in this order:
   *   1. Readability
   *   2. Goose
   *   3. Goose with some additional custom processing
   *   4. Custom methodology
   *
	 */
	public function processURL($url) {

		// Check for redirects first
		$url = $this->checkForRedirects($url);

    $this->log_debug("Attempting to parse " . $url);

		// If we don't have a force method enabled, then simply run them in the following order
		if ($this->force_method == null) {

			// First try with readability
			$results = $this->parseURLViaReadability($url);

			// If we don't see what we want, try our other method
	    if ($results['text'] == null) {
	      $results = $this->parseURLViaGoose($url);
	    }

			// If we still don't have text, then try our custom method passing in the results from the prior Goose Call
			if ($results['text'] == null) {
	      $results = $this->parseURLViaCustom($url, $results);
	    }

		}
		// Otherwise, run them specifically in this order
		else {
			switch($this->force_method) {
				case 'readability':
				  $results = $this->parseURLViaReadability($url);
					break;
				case 'goose':
					$results = $this->parseURLViaGoose($url);
					break;
				case 'goosecustom':
					$results = $this->parseURLViaGoose($url);
					if ($results['text'] == null) {
			      $results = $this->parseURLViaCustom($url, $results);
			    }
					break;
				case 'custom':
					$results = $this->parseURLViaCustom($url);
					break;
			}
		}

		// Add the resultant URL after redirects
		$results['result_url'] = $url;

		// Perform the post processing cleanup
    return $this->performHTMLPostProcessing($results);

  }

  /**
   * Performs post processing functions on the result array after the HTML is
   * either retrieved and processed.
   */
  private function performHTMLPostProcessing($results) {

    // POST PROCESSING

    // If we still don't havewhat we want, return what we have
    if ($results['text'] == null) {
      $results['language'] = null;
      $results['language_method'] = null;
      unset($results['html']); // remove raw HTML before returning it
      return $results;
    }

    // Otherwise, continue on...

    // Implement check in HTML to determine if the language is specified somewhere
		if ($lang_detect = $this->checkHTMLForLanguageHint($results['html'])) {
			$results['language_method'] = "html";
			$results['language'] = $lang_detect;
			$this->log_debug("performHTMLPostProcessing: Language was detected as " . $results['language'] . " from HTML");
		}

		$this->log_debug("performHTMLPostProcessing: --------- PRE UTF 8 CLEANING -------------------------------------");
		$this->log_debug("performHTMLPostProcessing: title: " . $results['title']);
		$this->log_debug("performHTMLPostProcessing: text: " . $results['text']);
		$this->log_debug("performHTMLPostProcessing: ------------------------------------------------------------------");

		// Convert items to UTF-8
		$results['title'] = $this->shiftEncodingToUTF8($results['title']);
		$results['text'] = $this->shiftEncodingToUTF8($results['text']);

		// If we've got some text, we still don't have a language, and we're configured with an API key...
		if ($results['text'] != null && !isset($results['language'])) {

			// If we have an API key
			if ($this->api_key != null) {

	      // Then use the service to detect the language
				$results['language_method'] = "service";
				$results['language'] = $this->identifyLanguage(mb_substr($results['text'],0,100));
				$this->log_debug("performHTMLPostProcessing: Language was detected as  " . $results['language'] . " from service");
			}
			// Otherwise skip
			else {
				$this->log_debug("performHTMLPostProcessing: Skipping remote language detection service check");
	      $results['language_method'] = null;
	      $results['language'] = null;
			}
		}

		$this->log_debug("performHTMLPostProcessing: text: " . $results['text']);
		$this->log_debug("performHTMLPostProcessing: title: " . $results['title']);
		$this->log_debug("performHTMLPostProcessing: language: " . $results['language']);
		$this->log_debug("performHTMLPostProcessing: parse_method: " . $results['parse_method']);
		$this->log_debug("performHTMLPostProcessing: language_method: " . $results['language_method']);

		if (array_key_exists('result_url', $results)) {
			$this->log_debug("performHTMLPostProcessing: result_url: " . $results['result_url']);
		}

    unset($results['html']); // remove raw HTML before returning it

    return $results;

  }

  /**
	 * Attempts to parse via the Readability libary and returns the following array.
   * [
   *    'method' => "readability"
   *    'title' => <the title of the article>
   *    'text' => <the cleaned text of the article> | null
   *    'html' => <the raw HTML of the article>
   * ]
   *
   * Parsing can be considered unavailable if 'text' is returned as null
	 */
  private function parseURLViaReadability($url) {

    $text = null;
    $title = null;
		$method = "readability";
		$html = null;

    try {
			if($this->user_agent != null) {
				$this->log_debug("Manually setting user agent for file_get_contents to " . $this->user_agent);
				$context = stream_context_create(array('http' => array('user_agent' => $this->user_agent)));
				$html = file_get_contents($url, false, $context);
			}
			else {
				$html = file_get_contents($url);
			}
      return $this->parseHTMLViaReadability($html);

    }
    catch (\Exception $e) {
      $this->log_debug('parseURLViaReadability: Error processing text', $e->getMessage());
    }

    return ['parse_method'=>$method, 'title'=>$title, 'text'=>$text, 'html'=>$html];

  }

  /**
	 * Attempts to parse HTML via the Readability libary and returns the following array.
   * [
   *    'method' => "readability"
   *    'title' => <the title of the article>
   *    'text' => <the cleaned text of the article> | null
   *    'html' => <the raw HTML of the article>
   * ]
   *
   * Parsing can be considered unavailable if 'text' is returned as null
	 */
  private function parseHTMLViaReadability($html) {

    $text = null;
    $title = null;
		$method = "readability";

    $readability = new Readability(new Configuration(['SummonCthulhu'=>true]));

    try {

      $readability->parse($html);
      $title = $readability->getTitle();
      $text = $readability->getContent();

			// Replace all <h*> and </h*> tags with newlines
			$text = preg_replace ('/<h[1-6]>/', "\n", $text);
			$text = preg_replace ('/<\/h[1-6]>/', "\n", $text);
			$text = preg_replace ('/<p>/', "\n", $text);
			$text = preg_replace ('/<\/p>/', "\n", $text);

      $text = strip_tags($text); // Remove all HTML tags
      $text = html_entity_decode($text); // Make sure we have no HTML entities left over

			$text = str_replace("\t", " ", $text); // Replace tabs with spaces
			$text = preg_replace('/ {2,}/', ' ', $text); // Remove multiple spaces

			$text = str_replace("\r", "\n", $text); // convert carriage returns to newlines
			$text = preg_replace("/(\n)+/", "$1", $text); // remove excessive line returns

    }
    catch (ParseException $e) {
      $this->log_debug('parseHTMLViaReadability: Error processing text', $e->getMessage());
    }

    return ['parse_method'=>$method, 'title'=>$title, 'text'=>$text, 'html'=>$html];

  }


	/**
	 * Attempts to parse via the Goose libary Returns the following array.
   * [
   *    'method' => "goose" | null
   *    'title' => <the title of the article>
   *    'text' => <the cleaned text of the article> | null
   *    'html' => <the raw HTML of the article>
   * ]
   *
   * Parsing can be considered unavailable if 'text' is returned as null
	 */
  private function parseURLViaGoose($url) {

    $text = null;
		$method = "goose";
    $title = null;
    $html = null;

    $this->log_debug("Parsing via: goose method");

		// Try to get the article using Goose first
		$goose = new GooseClient(['image_fetch_best' => false]);

    try {
      $article = $goose->extractContent($url);
      $title = $article->getTitle();
      $html = $article->getRawHtml();
			$text = $article->getCleanedArticleText();
			// If Goose failed, $text will be null here

    }
    catch (\Exception $e) {
      $this->log_debug('parseURLViaGoose: Unable to request url ' . $url . " due to " . $e->getMessage());
    }

    return ['parse_method'=>$method, 'title'=>$title, 'text'=>$text, 'html'=>$html];
	}


  /**
	 * Attempts to parse via the Goose libary with raw html available to it.
   * [
   *    'method' => "goose" | null
   *    'title' => <the title of the article>
   *    'text' => <the cleaned text of the article> | null
   *    'html' => <the raw HTML of the article>
   * ]
   *
   * Parsing can be considered unavailable if 'text' is returned as null
	 */
  private function parseHTMLViaGoose($html) {

    $text = null;
		$method = "goose";
    $title = null;

    $this->log_debug("Parsing via: goose method");

		// Try to get the article using Goose first
		$goose = new GooseClient(['image_fetch_best' => false]);

    try {
      $article = $goose->extractContent('http://nowhere.com', $html); // pass in the HTML instead
      $title = $article->getTitle();
			$text = $article->getCleanedArticleText();
			// If Goose failed, $text will be null here

    }
    catch (\Exception $e) {
      $this->log_debug('parseHTMLViaGoose: Unable to process HTML due to ' . $e->getMessage());
    }

    return ['parse_method'=>$method, 'title'=>$title, 'text'=>$text, 'html'=>$html];
	}



  /**
	 * Attempts to parse HTML via the Goose libary and our custom processing. Returns the
   * following array.
   * [
   *    'method' => "custom"
   *    'title' => <the title of the article>
   *    'text' => <the cleaned text of the article> | null
   *    'html' => <the raw HTML of the article>
   * ]
   *
   * Parsing can be considered unavailable if 'text' is returned as null
	 */
  private function parseHTMLViaCustom($html, $priorResults = null) {
    $method = "custom";
		$text = null;

    $this->log_debug("parseHTMLViaCustom: Parsing HTML via custom method");

    try {

			if($priorResults == null) {
				// Try to get the title and HTML text from Goose first
				$this->log_debug("parseHTMLViaCustom: Processing HTML via Goose");
				$goose = new GooseClient(['image_fetch_best' => false]);
				$article = $goose->extractContent('http://nowhere.com', $html);
				$title = $article->getTitle();
			}
			else {
				$this->log_debug("parseHTMLViaCustom: Using prior HTML and title from Goose");
				$title = $priorResults['title'];
			}

			// Run the custom post processing to get the text
			$text = $this->performCustomPostProcessing($html);

    }
		catch (\Exception $e) {
      $this->log_debug('parseHTMLViaCustom: Unable to process HTML due to ' . $e->getMessage());
    }

    return ['parse_method'=>$method, 'title'=>$title, 'text'=>$text, 'html'=>$html];

  }

  /**
	 * Attempts to parse a URL via the Goose libary and our custom processing. Returns the
   * following array.
   * [
   *    'method' => "custom"
   *    'title' => <the title of the article>
   *    'text' => <the cleaned text of the article> | null
   *    'html' => <the raw HTML of the article>
   * ]
   *
   * Parsing can be considered unavailable if 'text' is returned as null
	 */
  private function parseURLViaCustom($url, $priorResults = null) {

		$method = "custom";
		$text = null;

    $this->log_debug("parseURLViaCustom: Parsing URL via custom method");

    try {

			if($priorResults == null) {
				// Try to get the title and HTML text from Goose first
				$this->log_debug("parseURLViaCustom: Downloading HTML via Goose");
				$goose = new GooseClient(['image_fetch_best' => false]);
				$article = $goose->extractContent($url);
	      $title = $article->getTitle();
	      $html = $article->getRawHtml();
			}
			else {
				$this->log_debug("parseURLViaCustom: Using prior HTML and title from Goose");
				$title = $priorResults['title'];
				$html = $priorResults['html'];
			}

			// Run the custom post processing to get the text
			$text = $this->performCustomPostProcessing($html);

    }
    catch (\Exception $e) {
      $this->log_debug('parseURLViaCustom: Unable to request url ' . $url . " due to " . $e->getMessage());
    }

    return ['parse_method'=>$method, 'title'=>$title, 'text'=>$text, 'html'=>$html];

	}


  /**
   * This function handles the html and uses our custom processing to process
   * into regular text.
   *
   * Returns the cleaned text.
   */
  private function performCustomPostProcessing($html) {

    // Variable to hold our resultant text
    $text = null;

    //$this->log_debug("---- RAW HTML -----------------------------------------------------------------------------------");
    //$this->log_debug($html);
    //$this->log_debug("-------------------------------------------------------------------------------------------------");

    // Ok then try it a different way
    $dom = new Dom;
    $dom->loadStr($html, (new Options())->setWhitespaceTextNode(false));

    // First, just completely remove the items we don't even care about
    $nodesToRemove = $dom->find('script, style, header, footer, input, button, aside, meta, link');

    foreach($nodesToRemove as $node) {
      $node->delete();
      unset($node);
    }

    // Records to store information on the best dom element found thusfar
    $best_element = null;
    $best_element_wc = 0;
    $best_element_wc_ratio = -1;

    // $html = $dom->outerHtml;

    // Get a list of qualifying nodes we want to evaluate as the top node for content
    $candidateNodes = $this->buildAllNodeList($dom->root);
    $this->log_debug("performCustomPostProcessing: Candidate node count: " . count($candidateNodes));

    // Find a target best element
    foreach($candidateNodes as $node) {

      // Calculate the wordcount, whitecount, and wordcount ratio for the text within this element
      $this_element_wc = str_word_count($node->text(true));
      $this_element_whitecount = substr_count($node->text(true), ' ');
      $this_element_wc_ratio = -1;

      // If the wordcount is not zero, then calculation the wc ratio, otherwise set it to -1
      $this_element_wc_ratio = ($this_element_wc == 0) ? -1 : $this_element_whitecount / $this_element_wc;

      // Calculate the word count contribution for all children elements
      $children_wc = 0;
      $children_num = 0;
      foreach($node->getChildren() as $child) {
        if (in_array($child->tag->name(),$this->valid_root_elements)) {
          $children_num++;
          $children_wc += str_word_count($child->text(true));
        }
      }

      // This is the contribution for this particular element not including the children types above
      $this_element_wc_contribution = $this_element_wc - $children_wc;

      // Debug information on this element for development purposes
      $this->log_debug("performCustomPostProcessing: Element:\t". $node->tag->name() . "\tTotal WC:\t" . $this_element_wc . "\tTotal White:\t" . $this_element_whitecount . "\tRatio:\t" . number_format($this_element_wc_ratio,2) . "\tElement WC:\t" . $this_element_wc_contribution . "\tChildren WC:\t" . $children_wc . "\tChild Contributors:\t" . $children_num . "\tBest WC:\t" . $best_element_wc . "\tBest Ratio:\t" . number_format($best_element_wc_ratio,2) . " " . $node->getAttribute('class'));

      // Now check to see if this element appears better than any previous one

      // We do this by first checking to see if this element's WC contribution is greater than the previous
      if	($this_element_wc_contribution > $best_element_wc) {

        // If we so we then calculate the improvement ratio from the prior best and avoid division by 0
        $wc_improvement_ratio = ($best_element_wc == 0) ? 100 : $this_element_wc_contribution / $best_element_wc;

        // There are three conditions in which this candidate should be chosen
        //		1. The previous best is zero
        //		2. The new best is more than 10% greater WC contribution than the prior best
        //		3. The new element wc ratio is less than the existing best element's ratio

        if ( $best_element_wc == 0 || $wc_improvement_ratio	 >= 1.10 || $this_element_wc_ratio <= $best_element_wc_ratio) {
          $best_element_wc = $this_element_wc_contribution;
          $best_element_wc_ratio = $this_element_wc_ratio;
          $best_element = $node;
          $this->log_debug("performCustomPostProcessing: \t *** New best element ***");
        }
      }
    }

    // If we have a candidate element
    if ($best_element) {

      // Now we need to do some sort of peer analysis
      $best_element = $this->peerAnalysis($best_element);

      // Decode the text
      $text = html_entity_decode($this->getTextForNode($best_element));
    }

    // Return the text field
    return $text;
  }



  private function checkGoogleReferralUrl($url) {

		$parse_results = parse_url($url);

		if (isset($parse_results['host']) && isset($parse_results['path'])) {

			if (strtolower($parse_results['host']) == "www.google.com" && strtolower($parse_results['path'] = "/url")) {

				if (isset($parse_results['query'])) {

					$items = explode("&", $parse_results['query']);

					foreach($items as $item) {
						$parts = explode("=", $item);
						if ($parts[0] == 'url') {

							$url = urldecode($parts[1]);
						}
					}
				}
			}
		}

		return $url;

	}

	/**
	 * Checks for redirects given a URL. Will return the ultimate final URL if found within
	 * 5 redirects. Otherwise, it will return the last url it found and log too many redirects
	 */
	private function checkForRedirects($url, $count = 0) {
		$this->log_debug("Checking for redirects on " . $url . " count " . $count);

		// First check to see if we've been redirected too many times
		if ($count > 5) {
			$this->log_debug("Too many redirects");
			return $url;
		}

		$url = $this->checkGoogleReferralUrl($url);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_NOBODY, true);						// exclude the body from the request, we only want the header here
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

		// NOTE: We don't set user-agent here because many of the redirect services will use meta refresh instead of location headers to redirect.

		$a = curl_exec($ch);

		$new_url = $this->findLocationHeader($a);

		if($new_url != null) {
			$this->log_debug("Redirect found to: " . $new_url);

			// Check to see if new redirect has scheme and host
			$parse_results = parse_url($new_url);

			if ( !array_key_exists('scheme', $parse_results) && !array_key_exists('host', $parse_results) )  {

				// Use scheme, url, and host from passed in URL
				$old_parse_results = parse_url($url);
				$scheme_host = $old_parse_results['scheme'] . "://" . $old_parse_results['host'];
				if (isset($old_parse_results['port'])) {
					$scheme_host .= ":" . $parse_results['port'];
				}

				$full_url = $scheme_host . $new_url;

				$this->log_debug("No scheme and host found for: " . $new_url . " -- Utilizing prior redirect scheme and host: " . $full_url);
				$new_url = $full_url;
			}


			return $this->checkForRedirects($new_url, $count+1);
		}
		else {
			return $url;
		}
	}

	/**
	 * Looks for "Location:" or "location:" in the header. Returns null if it can't find it.
	 */
	private function findLocationHeader($text) {

		$lines = explode("\n", $text);

		foreach($lines as $line) {

			$header_item = explode(":", $line);

			if (mb_strtolower($header_item[0]) == "location") {
				$url = trim(mb_substr($line, mb_strpos($line,":")+1));
				return $url;
			}
		}

		return null;
	}

	/**
	 * Shifts encoding to UTF if needed
	 */
	private function shiftEncodingToUTF8($text) {

		if ($encoding = mb_detect_encoding($text, mb_detect_order(), true)) {
			$this->log_debug("shiftEncodingToUTF8 detected encoding of " . $encoding . " -> shifting to UTF-8");
			return iconv($encoding, "UTF-8", $text);
		}
		else {
			$this->log_debug("shiftEncodingToUTF8 detected NO encoding -> leaving as is");
			return $text;
		}
	}

	private function peerAnalysis($element) {

		$this->log_debug("PEER ANALYSIS ON " . $element->tag->name() . " (" . $element->getAttribute('class') . ")");

		$range = 0.50;

		$element_wc = str_word_count($element->text(true));
		$element_whitecount = substr_count($element->text(true), ' ');
		$element_wc_ratio = $element_whitecount / $element_wc;

		if ($element->getParent() != null) {

			$parent = $element->getParent();
			$this->log_debug("	Parent: " . $parent->tag->name() . " (" . $parent->getAttribute('class') . ")");

			$peers_with_close_wc = 0;

			foreach($parent->getChildren() as $child)
			{
				$child_wc = str_word_count($child->text(true));
				$child_whitecount = substr_count($child->text(true), ' ');

				if ($child_wc != 0) {
					$child_wc_ratio = $child_whitecount / $child_wc;

					$this->log_debug("	  Child: " . $child->tag->name() . " (" . $child->getAttribute('class') . ") WC: " . $child_wc . " Ratio: " . number_format($child_wc_ratio,2) );

					if ($child_wc > ($element_wc * $range) && $child_wc < ($element_wc * (1 + $range))) {
						$this->log_debug("** good peer found **");
						$peers_with_close_wc++;
					}
				}
			}

			if ($peers_with_close_wc > 2) {
				$this->log_debug("Returning parent");
				return $parent;
			}
			else {
				$this->log_debug("Not enough good peers, returning original element");
				return $element;
			}
		}
		else {
			$this->log_debug("Element has no parent - returning original element");
			return $element;
		}
	}

	private function buildAllNodeList($element, $depth = 0) {

		$return_array = array();

		// Debug what we are checking

		if($element->getTag()->name() != "text") {

			$this->log_debug("buildAllNodeList: " . str_repeat(' ', $depth*2) . $element->getTag()->name() . " ( " . $element->getAttribute('class') . " )");

			// Look at each child div element
			if ($element->hasChildren()) {

				foreach($element->getChildren() as $child)
				{
					// Push the children's children
					$return_array = array_merge($return_array, array_values($this->buildAllNodeList($child, $depth+1)));

					// Include the following tags in the counts for children and number of words
					if (in_array($child->tag->name(),$this->valid_root_elements)) {
						array_push($return_array, $child);
					}
				}
			}
		}
		else {
			$this->log_debug("buildAllNodeList: " . str_repeat(' ', $depth*2) . $element->getTag()->name());
		}
		return $return_array;
	}

	private function log_debug($message) {
		if ($this->debug) {
			echo $message . "\n";
		}
	}

	/*
	 * This function gets the text representation of a node and works recursively to do so.
	 * It also trys to format an extra space in HTML elements that create concatenation
	 * issues when they are slapped together
	 */
	private function getTextForNode($element) {

		$text = '';

		$this->log_debug("getTextForNode: "	 . $element->getTag()->name());

		// Look at each child
		foreach ($element->getChildren() as $child) {

			// If its a text node, just give it the nodes text
			if ($child instanceof TextNode) {
				$text .= $child->text();
			}
			// Otherwise, if it is an HtmlNode
			elseif ($child instanceof HtmlNode) {

				// If this is one of the HTML tags we want to add a space to
				if (in_array($child->getTag()->name(),$this->space_elements)) {
					$text .= " " . $this->getTextForNode($child);
				}
				else {
					$text .= $this->getTextForNode($child);
				}
			}
		}

		// Return our text string
		return $text;
	}


	/**
	 * Identifies the language received in the UTF-8 text using the DetectLanguage API key.
	 * Returns false if the language could not be identified and the ISO code if it can be
	 */
	private function identifyLanguage($text) {
		$this->log_debug("identifyLanguage: " . $text);

		if ($this->api_key == null) {
			$this->log_debug("identifyLanguage: Cannot detect language. No api key passed in");
			return false;
		}

    try {
			// Set the API key for detect language library
			DetectLanguage::setApiKey($this->api_key);

			// Detect the language
			$languageCode = DetectLanguage::simpleDetect($text);

			if ($languageCode == null) {
				return false;
			}
			else {
				return $languageCode;
			}
		}
		catch (\Exception $e) {
			$this->log_debug("identifyLanguage: Error with DetectLanguage routine. Returning false: Message is " . $e->getMessage());
			return false;
		}
	}

	/**
	 * Checks the passed in HTML for any hints within the HTML for language. Should
	 * return the ISO 639-1 language code if found or false if no language could be determined
	 * from the dom model.
	 *
	 */
	private function checkHTMLForLanguageHint($html_string) {

    try {
      // Ok then try it a different way
  		$dom = new Dom;
  		$dom->loadStr($html_string, (new Options())->setWhitespaceTextNode(false));

  		$htmltag = $dom->find('html');
  		$lang = $htmltag->getAttribute('lang');

  		// Check for lang in HTML tag
  		if ($lang != null) {
  			$this->log_debug("checkHTMLForLanguageHint: Found language: " . $lang . ", returning " . substr($lang,0,2));
  			return substr($lang,0,2);
  		}
  		// Otherwise...
  		else {

  			// Check to see if we have a <meta name="content-language" content="ja" /> type tag
  			$metatags = $dom->find("meta");

  			foreach ($metatags as $tag) {
  				$this->log_debug("Checking tag: " . $tag->getAttribute('name'));
  				if ($tag->getAttribute('name') == 'content-language') {
  					return $tag->getAttribute('content');
  				}
  			}

  			$this->log_debug("checkHTMLForLanguageHint: Found no language");
  			return false;
  		}
    }
    catch (\Exception $e) {
      $this->log_debug("checkHTMLForLanguageHint: Returning false as exception occurred: " . $e->getMessage());
      return false;
    }

	}

	/* *
	 *
	function translateText($text, $targetLang)
	{
		$baseUrl = "https://translate.yandex.net/api/v1.5/tr.json/translate?key=YOUR_yandex_api_key";
		$url = $baseUrl . "&text=" . urlencode($text) . "&lang=" . urlencode($targetLang);

		$ch = curl_init($url);

		curl_setopt($ch, CURLOPT_CAINFO, YOUR_CERT_PEM_FILE_LOCATION);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

		$output = curl_exec($ch);
		if ($output)
		{
			$outputJson = json_decode($output);
			if ($outputJson->code == 200)
			{
				if (count($outputJson->text) > 0 && strlen($outputJson->text[0]) > 0)
				{
					return $outputJson->text[0];
				}
			}
		}

		return $text;
	}
	*/
}

?>
