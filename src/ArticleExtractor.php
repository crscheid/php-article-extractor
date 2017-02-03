<?php 

namespace Cscheide\ArticleExtractor;

use Goose\Client as GooseClient;
use PHPHtmlParser\Dom;
 
class ArticleExtractor {

	// Debug flag - set to true for convenience during development
	private $debug = false;
	
	// Valid root elements we want to search for
	private $valid_root_elements = [ 'body', 'form', 'main', 'div', 'ul', 'li', 'table', 'span', 'section','article'];
 
	public function getArticleText($url) {
		$text = null;
		$method = "goose";

		// Try to get the article using Goose first
		$goose = new GooseClient(['image_fetch_best' => false]);
		$article = $goose->extractContent($url);
	
		// If Goose failed
		if ($article->getCleanedArticleText() == null) {
	
			// Get the HTML from goose
			$html_string = $article->getRawHtml();
	
			// Ok then try it a different way
			$dom = new Dom;
			$dom->load($html_string, ['whitespaceTextNode' => false]);
		
			// First, just completely remove the items we don't even care about		
			$scriptNodes = $dom->find('script, style, header, footer, input, form, button, aside, meta, link');
			foreach($scriptNodes as $node) {
				$node->delete();
				unset($node);
			}		

			// Records to store information on the best dom element found thusfar
			$best_element = null;
			$best_element_wc = 0;
			$best_element_wc_ratio = -1; 
			
			$html = $dom->outerHtml;

			// Get a list of qualifying nodes we want to evaluate as the top node for content
			$contentList = $this->buildAllNodeList($dom->root);

			// Find a target best element
			foreach($contentList as $node) {

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
				$this->log_debug("Element:\t". $node->tag->name() . "\tTotal WC:\t" . $this_element_wc . "\tTotal White:\t" . $this_element_whitecount . "\tRatio:\t" . number_format($this_element_wc_ratio,2) . "\tElement WC:\t" . $this_element_wc_contribution . "\tChildren WC:\t" . $children_wc . "\tChild Contributors:\t" . $children_num . "\tBest WC:\t" . $best_element_wc . "\tBest Ratio:\t" . number_format($best_element_wc_ratio,2) . " " . $node->getAttribute('class'));

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
						$this->log_debug("\t *** New best element ***");
					}					
				}
			}
			
			// Now we need to do some sort of peer analysis
			
			//$best_element = $this->peerAnalysis($best_element);
			
			
			if ($best_element) {
				$text = html_entity_decode($best_element->text(true));
				$method = "custom";
			}
			else {
				$method = null;
			}
		}
		else {
			$text = $article->getCleanedArticleText();
		}

		// Convert items to UTF-8
		$clean_utf_title = iconv(mb_detect_encoding($article->getTitle(), mb_detect_order(), true), "UTF-8", $article->getTitle());
		$clean_utf_text = iconv(mb_detect_encoding($text, mb_detect_order(), true), "UTF-8", $text);

		$this->log_debug("TITLE: " . $clean_utf_title);
		$this->log_debug("METHOD: " . $method);
		$this->log_debug("CONTENT: " . $clean_utf_text);

		return ['title'=>$clean_utf_title,'text'=>$clean_utf_text,'method'=>$method];
	}

	private function peerAnalysis($element) {

		$this->log_debug("PEER ANALYSIS ON " . $element->tag->name() . " (" . $element->getAttribute('class') . ")");

		$range = 0.50;
	
		$element_wc = str_word_count($element->text(true));
		$element_whitecount = substr_count($element->text(true), ' ');
		$element_wc_ratio = $element_whitecount / $element_wc;
	
		if ($element->getParent() != null) {

			$parent = $element->getParent();
			$this->log_debug("  Parent: " . $parent->tag->name() . " (" . $parent->getAttribute('class') . ")");

			$peers_with_close_wc = 0;

			foreach($parent->getChildren() as $child)
			{
				$child_wc = str_word_count($child->text(true));
				$child_whitecount = substr_count($child->text(true), ' ');

				if ($child_wc != 0) {
					$child_wc_ratio = $child_whitecount / $child_wc;

					$this->log_debug("    Child: " . $child->tag->name() . " (" . $child->getAttribute('class') . ") WC: " . $child_wc . " Ratio: " . number_format($child_wc_ratio,2) );
					
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

	private function buildAllNodeList($element) {

		$return_array = array();

		if($element->getTag()->name() != "text") {

			// Look at each child div element
			if ($element->hasChildren()) {

				foreach($element->getChildren() as $child)
				{
					// Push the children's children
					$return_array = array_merge($return_array, array_values($this->buildAllNodeList($child)));
	
					// Include the following tags in the counts for children and number of words
					if (in_array($child->tag->name(),$this->valid_root_elements)) {
						array_push($return_array, $child);
					}
				}
			}
		}
		return $return_array;
	}


	private function log_debug($message) {
		if ($this->debug) {
			echo $message . "\n";
		}
	}
}

?>