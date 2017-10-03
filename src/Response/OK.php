<?php declare(strict_types=1);

namespace Sturdy\Activity\Response;

use stdClass;
use Sturdy\Activity\Resource;

final class OK implements Response
{
	use ProtocolVersionTrait;
	use DateTrait;
	use NoLocationTrait;

	private $resource;
	private $parts;
	private $part;

	/**
	 * Constructor
	 *
	 * @param Resource $resource  the resource
	 */
	public function __construct(Resource $resource)
	{
		$this->resource = $resource;
		$this->parts = new stdClass;
		$this->parts->main = new stdClass;
		$this->part = $this->parts->main;
	}

	/**
	 * Get the response status code
	 *
	 * @return int  the status code
	 */
	public function getStatusCode(): int
	{
		return 200;
	}

	/**
	 * Get the response status text
	 *
	 * @return string  the status text
	 */
	public function getStatusText(): string
	{
		return "OK";
	}

	/**
	 * Get content type
	 *
	 * @return string  the content type
	 */
	public function getContentType(): string
	{
		return "application/json";
	}

	/**
	 * Get content
	 *
	 * @return string
	 */
	public function getContent(): string
	{
		return json_encode($this->parts, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
	}

	/**
	 * Set the fields of this response.
	 *
	 * @param array $fields
	 * @return self
	 */
	public function setFields(array $fields): void
	{
		$this->part->fields = new stdClass;
		foreach ($fields as $name => $field) {
			if ($field->meta??false) {
				$this->part->fields->$name = $field;
			} elseif ($field->data??false) {
				$this->part->fields->$name = $field;
				$this->part->data = $field->value;
				unset($field->value);
			} else {
				if (!isset($this->part->data)) {
					$this->part->data = new stdClass;
				}
				$this->part->fields->$name = $field;
				$this->part->data->$name = $field->value??null;
				unset($field->value);
			}
		}
	}

	/**
	 * Link to another resource.
	 *
	 * @param  string $name    the name of the link
	 * @param  string $class   the class of the resource
	 * @param  array  $values  the values in case the resource has uri fields
	 * @param  bool   $attach  also attach the resource in the response
	 *
	 * Please note that $attach is ignored if link is called from a Resource
	 * that itself is attached by another resource.
	 */
	public function link(string $name, string $class, array $values = [], bool $attach = false): void
	{
		$link = $this->resource->createLink($class, $values);
		if ($link === null) return;
		if (!isset($this->part->links)) {
			$this->part->links = new stdClass();
		}
		$this->part->links->$name = $link->toArray();
		if ($attach && $this->part === $this->parts->main) { // only attach if linking from first resource
			$resource = $this->resource->createAttachedResource($class);
			$previous = $this->part;
			$this->parts->$name = $this->part = new stdClass;
			$resource->call($values);
			$this->part = $previous;
		}
	}
}
