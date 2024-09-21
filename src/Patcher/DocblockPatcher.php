<?php

namespace Kestrel\Aviary\Patcher;

use Humbug\PhpScoper\Patcher\Patcher;
use Humbug\PhpScoper\Symbol\Reflector;

/**
 * Custom patcher for PHP Scoper to prefix class names in docblocks.
 */
final class DocblockPatcher implements Patcher
{
	protected Reflector $reflector;

	public function __construct(protected array $config)
	{
		$this->reflector = Reflector::createWithPhpStormStubs();
	}

	public function __invoke(string $filePath, string $prefix, string $contents): string
	{
		// Pattern for @param, @return, @var, @see, and @throws annotations
		$pattern1 = '/@(?:param|return|var|throws|see)\s+\\\\([a-zA-Z_][\w\\\\]*)/';

		// Pattern for class-string<> instances
		$pattern2 = '/class-string<\\\\([a-zA-Z_][\w\\\\]*)>/';

		$contents = preg_replace_callback($pattern1, fn($matches) => $this->replaceCallback($matches, $prefix), $contents);
		$contents = preg_replace_callback($pattern2, fn($matches) => $this->replaceCallback($matches, $prefix), $contents);

		return $contents;
	}

	protected function replaceCallback(array $matches, string $prefix): string
	{
		$symbol = $matches[1];

		// Ignore if the symbol is already prefixed
		if (str_starts_with($symbol, $prefix . '\\')) {
			return $matches[0];
		}

		// Ignore if the symbol is in the exclude list or is internal
		if ($this->isExcludedOrInternal($symbol)) {
			return $matches[0];
		}

		return str_replace($symbol, $prefix . '\\' . $symbol, $matches[0]);
	}

	/**
	 * Determine if a symbol should be excluded or is internal.
	 *
	 * Since there's no way to know what type the symbol inside a docblock is,
	 * we check for excluded/internal classes, functions and constants.
	 */
	protected function isExcludedOrInternal(string $symbol): bool
	{
		return $this->isClassExcluded($symbol)
			|| $this->isClassInternal($symbol)
			|| $this->isFunctionExcluded($symbol)
			|| $this->isFunctionInternal($symbol)
			|| $this->isConstantExcluded($symbol)
			|| $this->isConstantInternal($symbol);
	}

	protected function isClassExcluded(string $symbol): bool
	{
		return in_array($symbol, $this->config['exclude-classes'], true);
	}

	protected function isClassInternal(string $symbol): bool
	{
		return $this->reflector->isClassInternal($symbol);
	}

	protected function isFunctionExcluded(string $symbol): bool
	{
		return in_array($symbol, $this->config['exclude-functions'], true);
	}

	protected function isFunctionInternal(string $symbol): bool
	{
		return $this->reflector->isFunctionInternal($symbol);
	}

	protected function isConstantExcluded(string $symbol): bool
	{
		return in_array($symbol, $this->config['exclude-constants'], true);
	}

	protected function isConstantInternal(string $symbol): bool
	{
		return $this->reflector->isConstantInternal($symbol);
	}

}