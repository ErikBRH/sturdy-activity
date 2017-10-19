<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta\Type;

use stdClass;

/**
 * Integer type
 */
final class IntegerType extends Type
{
	const type = "integer";
	private $minimumRange;
	private $maximumRange;
	private $step;

	/**
	 * Constructor
	 *
	 * @param string|null $state  the objects state
	 */
	public function __construct(string $state = null)
	{
		if ($state !== null) {
			[$min, $max, $step] = explode(",", $state);
			$this->minimumRange = strlen($min) ? (int)$min : null;
			$this->maximumRange = strlen($max) ? (int)$max : null;
			$this->step = strlen($step) ? (int)$step : null;
		}
	}

	/**
	 * Set meta properties on object
	 *
	 * @param stdClass $meta
	 */
	public function meta(stdClass $meta): void
	{
		$meta->type = self::type;
		if (isset($this->minimumRange)) {
			$meta->min = $this->minimumRange;
		}
		if (isset($this->maximumRange)) {
			$meta->max = $this->maximumRange;
		}
		if (isset($this->step)) {
			$meta->step = $this->step;
		}
	}

	/**
	 * Get descriptor
	 *
	 * @return string
	 */
	public function getDescriptor(): string
	{
		return self::type.":".$this->minimumRange.",".$this->maximumRange.",".$this->step;
	}

	/**
	 * Set minimum range
	 *
	 * @param ?int $minimumRange
	 * @return self
	 */
	public function setMinimumRange(?int $minimumRange): self
	{
		$this->minimumRange = $minimumRange;
		return $this;
	}

	/**
	 * Get minimum range
	 *
	 * @return ?int
	 */
	public function getMinimumRange(): ?int
	{
		return $this->minimumRange;
	}

	/**
	 * Set maximum range
	 *
	 * @param ?int $maximumRange
	 * @return self
	 */
	public function setMaximumRange(?int $maximumRange): self
	{
		$this->maximumRange = $maximumRange;
		return $this;
	}

	/**
	 * Get maximum range
	 *
	 * @return ?int
	 */
	public function getMaximumRange(): ?int
	{
		return $this->maximumRange;
	}

	/**
	 * Set step
	 *
	 * @param ?int $step
	 * @return self
	 */
	public function setStep(?int $step): self
	{
		$this->step = $step;
		return $this;
	}

	/**
	 * Get step
	 *
	 * @return ?int
	 */
	public function getStep(): ?int
	{
		return $this->step;
	}

	/**
	 * Filter value
	 *
	 * @param  &$value integer the value to filter
	 * @return bool  whether the value is valid
	 */
	public function filter(&$value): bool
	{
		$integer = false;
		
		if(is_string($value)) {
			$integer = filter_var(trim($value), FILTER_VALIDATE_INT);
		} else {
			$integer = filter_var($value, FILTER_VALIDATE_INT);
		}
		if ($integer === false) {
			return false;
		}
		if (isset($this->minimumRange) && $integer < $this->minimumRange) {
			return false;
		}
		if (isset($this->maximumRange) && $integer > $this->maximumRange) {
			return false;
		}
		if (isset($this->step) && 0 !== (($integer - ($this->minimumRange ?? 0)) % $this->step)) {
			return false;
		}
		$value = $integer;
		return true;
	}
}
