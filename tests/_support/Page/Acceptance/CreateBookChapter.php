<?php
namespace Page\Acceptance;

use AcceptanceTester;

class CreateBookChapter
{
	public static $URL = '/wp-admin/post-new.php?post_type=chapter';

	protected $titleField = '#title';
	protected $saveButton = '#publishing-action input[type=submit]';

	/**
	 * @var AcceptanceTester;
	 */
	protected $acceptanceTester;

	public function __construct(AcceptanceTester $I)
	{
		$this->acceptanceTester = $I;
	}

	public function createChapter(string $bookURL, string $title, string $content = null): void
	{
		$I = $this->acceptanceTester;

		$I->amOnPage( $bookURL . self::$URL);
		$I->fillField($this->titleField, $title);
		$I->click($this->saveButton);
	}
}
