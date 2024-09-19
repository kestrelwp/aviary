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
		// look for @param, @return, @var, and @throws annotations
		$pattern = '/@(?:param|return|var|throws)\s+(?:class-string<\\\\([a-zA-Z_][\w\\\\]*)>|\\\\([a-zA-Z_][\w\\\\]*))/';

		return preg_replace_callback($pattern, function ($matches) use ($prefix) {
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
		}, $contents);
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