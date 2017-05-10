<?php
 
use Cscheide\ArticleExtractor\ArticleExtractor;
 
class ExtractorTest extends PHPUnit_Framework_TestCase {

	private $problem_sites = [
		'https://www.fastcompany.com/3067246/innovation-agents/the-unexpected-design-challenge-behind-slacks-new-threaded-conversations',
		'http://www.mckinsey.com/industries/financial-services/our-insights/engaging-customers-the-evolution-of-asia-pacific-digital-banking?cid=other-eml-alt-mip-mck-oth-1701',
		'http://www.nhregister.com/opinion/20170116/poor-elijahs-almanack-some-choice-observations',
		'http://www.mckinsey.com/business-functions/digital-mckinsey/our-insights/the-next-generation-operating-model-for-the-digital-world?cid=reinventing-eml-alt-mip-mck-oth-1703',
		'https://hbr.org/2017/03/the-promise-of-blockchain-is-a-world-without-middlemen',
		'https://www.bloomberg.com/news/articles/2017-03-13/bitcoin-miners-signal-revolt-in-push-to-fix-sluggish-blockchain',
		'https://t.co/kwb19AGfxl',
		'http://www.slate.com/articles/news_and_politics/war_stories/2017/01/trump_talks_about_himself_complains_about_media_at_first_official_event.html',
		'http://calnewport.com/blog/2017/03/13/yuval-harari-works-less-than-you/?utm_source=pocket&utm_medium=email&utm_campaign=pockethits',
		'http://www.asahi.com/articles/ASK5B4HSVK5BUHBI01L.html?iref=comtop_latestnews_03',
		'http://www3.nhk.or.jp/news/html/20170510/k10010976181000.html',
	];

	private $known_problems = [
		// Some sort of problem with the DOM model reading the nesting of the <li> elements the wrong way - its backing out of the div too early
		'http://kotaku.com/nintendo-switch-the-kotaku-review-1792776350?utm_source=pocket&utm_medium=email&utm_campaign=pockethits',

		// React problem #5 (https://github.com/crscheid/php-article-extractor/issues/5)
		'https://www.bitcoinunlimited.info/faq',

		// Related to (https://github.com/scotteh/php-dom-wrapper/issues/4)
		'http://futurememes.blogspot.jp/2017/01/cognitive-easing-human-identity-crisis.html?m=1',
		
		// HTML doesn't actually contain the story without first loading some client-side javascript, similar to #5 above
		'http://mw.nikkei.com/sp/#!/article/DGXLASJC20H12_Q7A420C1000000',
		
		// Unknown reason why
		'http://gizmodo.com/how-to-survive-the-next-catastrophic-pandemic-1793487027?utm_source=pocket&utm_medium=email&utm_campaign=pockethits',
	];
 
	public function testProblemSites()
	{
		echo "\n";
		
		foreach($this->problem_sites as $url) {
			$parser = new ArticleExtractor();
			echo "Testing: " . $url . "\n";
			
			$result = $parser->getArticleText($url);
			$this->assertNotEmpty($result['title']);
			$this->assertNotEmpty($result['text']);
			$this->assertNotEmpty($result['language']);
			$this->assertNotEmpty($result['parse_method']);
			$this->assertNotEmpty($result['language_method']);
		}
	}


}