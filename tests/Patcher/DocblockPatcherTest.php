<?php

declare(strict_types=1);

namespace Kestrel\Aviary\Tests\Patcher;

use Kestrel\Aviary\Patcher\DocblockPatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DocblockPatcher::class)]
class DocblockPatcherTest extends TestCase
{
	#[DataProvider('provideFiles')]
	public function test_patch_docblocks(string $filePath, string $contents, string $expected): void
	{
		$config = [
			'exclude-classes' => ['ExcludedClass', 'AnotherExcludedClass'],
			'exclude-functions' => [],
			'exclude-constants' => [],
		];

		$result = (new DocblockPatcher($config))($filePath, 'Scoped', $contents);

		self::assertSame($expected, $result);
	}

	public static function provideFiles(): iterable
	{
		yield [
			'some/file/path.php',
			<<<'PHP'
				/**
				 * @param  \SomeClass $paramA
				 * @param  class-string<\SomeNamespace\SomeNamespacedClass> $paramB
				 * @param  string|class-string<\SomeNamespace\SomeNamespacedClass> $paramC
				 * @param  \ExcludedClass $paramD
				 * @param  \AnotherExcludedClass $paramE
				 * @return \AnotherClass
				 * @throws \Exception
				 */
				PHP,
			<<<'PHP'
				/**
				 * @param  \Scoped\SomeClass $paramA
				 * @param  class-string<\Scoped\SomeNamespace\SomeNamespacedClass> $paramB
				 * @param  string|class-string<\Scoped\SomeNamespace\SomeNamespacedClass> $paramC
				 * @param  \ExcludedClass $paramD
				 * @param  \AnotherExcludedClass $paramE
				 * @return \Scoped\AnotherClass
				 * @throws \Exception
				 */
				PHP,
		];

		yield [
			'some/other/path.php',
			<<<'PHP'
				/** @var \SomeClass $something */
				PHP,
			<<<'PHP'
				/** @var \Scoped\SomeClass $something */
				PHP,
		];

		yield [
			'yet/another/path.php',
			<<<'PHP'
				/** @see \AnotherClass */
				PHP,
			<<<'PHP'
				/** @see \Scoped\AnotherClass */
				PHP,
		];
	}
}