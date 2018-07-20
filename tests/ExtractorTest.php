<?php

use Cscheide\ArticleExtractor\ArticleExtractor;
use PHPUnit\Framework\TestCase;

class ExtractorTest extends TestCase {

	private $problem_sites = [
		'https://www.fastcompany.com/3067246/innovation-agents/the-unexpected-design-challenge-behind-slacks-new-threaded-conversations',
		'http://www.mckinsey.com/industries/financial-services/our-insights/engaging-customers-the-evolution-of-asia-pacific-digital-banking?cid=other-eml-alt-mip-mck-oth-1701',
		'http://www.nhregister.com/opinion/20170116/poor-elijahs-almanack-some-choice-observations',
		'http://www.mckinsey.com/business-functions/digital-mckinsey/our-insights/the-next-generation-operating-model-for-the-digital-world?cid=reinventing-eml-alt-mip-mck-oth-1703',
		'https://t.co/kwb19AGfxl',	// Redirects
		'http://www.slate.com/articles/news_and_politics/war_stories/2017/01/trump_talks_about_himself_complains_about_media_at_first_official_event.html',
		'http://calnewport.com/blog/2017/03/13/yuval-harari-works-less-than-you/?utm_source=pocket&utm_medium=email&utm_campaign=pockethits',
		'https://www.worldjournal.com/5683780/article-小唐納傳會女俄諜操控者-檢方：fbi有音檔/?ref=首頁_今日重點',	// Chinese
		'http://www.dn.pt/sociedade/interior/ele-e-uma-estrela-pop-ele-e-o-papa-do-povo-8472352.html',  // Portuguese
		'https://hbr.org/2017/03/the-promise-of-blockchain-is-a-world-without-middlemen',				// Requires remote language check
		'https://abcnews.go.com/Politics/wireStory/trump-tweets-nice-note-north-koreas-kim-jong-56544275',
		'http://kotaku.com/nintendo-switch-the-kotaku-review-1792776350?utm_source=pocket&utm_medium=email&utm_campaign=pockethits',
		'http://futurememes.blogspot.jp/2017/01/cognitive-easing-human-identity-crisis.html?m=1',
		'https://medium.com/personal-growth/thank-god-i-dont-have-a-job-ae2fcb6c096e',
		'https://www.cnbc.com/amp/2017/03/21/former-google-exec-shares-the-no-1-trait-great-bosses-share.html',
		'http://www.cnn.com/2017/04/07/politics/russia-us-syria-strike/index.html?adkey=bn',
		'http://gizmodo.com/how-to-survive-the-next-catastrophic-pandemic-1793487027?utm_source=pocket&utm_medium=email&utm_campaign=pockethits',
	];

	private $known_problems = [

		// React problem #5 (https://github.com/crscheid/php-article-extractor/issues/5)
		'https://www.bitcoinunlimited.info/faq',

		// HTML doesn't actually contain the story without first loading some client-side javascript, similar to #5 above
		'http://mw.nikkei.com/sp/#!/article/DGXLASJC20H12_Q7A420C1000000',

		// Temporary redirect 307 to terms of service voilation which prevents link from resolving
		'https://www.bloomberg.com/news/articles/2018-07-12/jpmorgan-wells-fargo-may-go-back-to-basics-with-loans-in-focus',

		// Multiple redirects
		'https://slashdot.org/story/18/07/19/2050244/microsofts-plan-to-try-to-win-back-consumers-with-modern-life-services',


	];

	public function testProblemSites()
	{
		echo "\n";

		foreach($this->problem_sites as $url) {
			$parser = new ArticleExtractor(getenv('DETECT_LANGUAGE_KEY'));
			echo "Testing: " . $url . "\n";

			$result = $parser->processURL($url);
			$this->assertNotEmpty($result['title']);
			$this->assertNotEmpty($result['text']);
			$this->assertNotEmpty($result['language']);
			$this->assertNotEmpty($result['parse_method']);
			$this->assertNotEmpty($result['language_method']);
		}
	}


}
