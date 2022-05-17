<?php

namespace Zotlabs\Tests\Unit\Photo;

use Zotlabs\Photo\PhotoGd;
use phpmock\phpunit\PHPMock;
use Zotlabs\Tests\Unit\UnitTestCase;

/**
 * @brief PhotoGd test case.
 *
 * These tests are not really useful yet, just some obvious behaviour.
 *
 * @todo Compare the actual results.
 * @todo Test different image types.
 */
class PhotoGdTest extends UnitTestCase {

	use PHPMock;

	/**
	 * @var PhotoGd
	 */
	private $photoGd;

	/**
	 * Prepares the environment before running a test.
	 */
	protected function setUp(): void {
		parent::setUp();

		$data = file_get_contents('images/hz-16.png');

		$this->photoGd = new PhotoGd($data, 'image/png');
	}

	/**
	 * Cleans up the environment after running a test.
	 */
	protected function tearDown(): void {
		$this->photoGd = null;

		parent::tearDown();
	}

	/**
	 * Tests PhotoGd->supportedTypes()
	 *
	 * Without mocking gd this check is environment dependent.
	 *
	public function testSupportedTypes() {
		$sft = $this->photoGd->supportedTypes();

		$this->assertArrayHasKey('image/jpeg', $sft);
		$this->assertArrayHasKey('image/gif', $sft);
		$this->assertArrayHasKey('image/png', $sft);

		$this->assertArrayNotHasKey('image/foo', $sft);
	}
	*/

	/**
	 * Tests PhotoGd->clearexif()
	 */
	public function testClearexifIsNotImplementedInGdAndDoesNotAlterImageOrReturnSomething() {
		$data_before = $this->photoGd->getImage();
		$this->assertNull($this->photoGd->clearexif());
		$this->assertSame($data_before, $this->photoGd->getImage());
	}

	/**
	 * Tests PhotoGd->getImage()
	 */
	/* TODO: fix for PHP8
	public function testGetimageReturnsAResource() {
		$res = $this->photoGd->getImage();
		$this->assertIsResource($res);
		$this->assertEquals('gd', get_resource_type($res));
	}
	*/

	public function testGetimageReturnsFalseOnFailure() {
		$this->photoGd = new PhotoGd('');
		$this->assertFalse($this->photoGd->getImage());
	}

	/**
	 * Tests PhotoGd->doScaleImage()
	 */
	public function testDoscaleImageSetsCorrectDimensions() {
		$this->photoGd->doScaleImage(5, 8);

		$this->assertSame(5, $this->photoGd->getWidth());
		$this->assertSame(8, $this->photoGd->getHeight());
	}

	/**
	 * Tests PhotoGd->rotate()
	 */
	 /* TODO: fix for PHP8
	public function testRotate360DegreesCreatesANewImage() {
		$data = $this->photoGd->getImage();
		$this->photoGd->rotate(360);
		$this->assertNotEquals($data, $this->photoGd->getImage());
	}
	*/

	/**
	 * Tests PhotoGd->flip()
	 *
	public function testFlip() {
		// TODO Auto-generated PhotoGdTest->testFlip()
		$this->markTestIncomplete("flip test not implemented");

		$this->photoGd->flip();
	}
	*/

	/**
	 * Tests PhotoGd->cropImageRect()
	 */
	public function testCropimagerectSetsCorrectDimensions() {
		$this->photoGd->cropImageRect(10, 12, 1, 2, 11, 11);

		$this->assertSame(10, $this->photoGd->getWidth());
		$this->assertSame(12, $this->photoGd->getHeight());
	}

	/**
	 * Tests PhotoGd->imageString()
	 */
	public function testImagestringReturnsABinaryString() {
		// Create a stub for global function get_config()
		// get_config('system', 'png_quality')
		// get_config('system', 'jpeg_quality');
		$gc = $this->getFunctionMock('Zotlabs\Photo', 'get_config');
		$gc->expects($this->once())->willReturnCallback(
				function() {
					switch($this->photoGd->getType()){
						case 'image/png':
							return 7;
						case 'image/jpeg':
						default:
							return 70;
					}
				}
		);

		$this->assertIsString($this->photoGd->imageString());
	}

}
