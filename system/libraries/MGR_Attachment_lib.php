<?php

defined('BASEPATH') or exit('No direct script access allowed');

// Path: application/libraries/Attachment_lib.php
// Library to manage attachments

class MGR_Attachment_lib
{
	protected const int VERSION_SUFFIX_LENGTH = 11; // '?v=' + dechex(time())

	/** Width of the package `attachment.title` column; a project with a wider table overrides it. */
	protected int $title_max_length = 100;

	/** Width of the package `attachment.file_name` column; a project with a wider table overrides it. */
	protected int $file_name_max_length = 100;

	/**
	 * Settings passed to upload_lib as `upload_config`; null keeps its defaults.
	 * A non-null array replaces the defaults entirely, so include `allowed_types` and `max_size`.
	 *
	 * @var array<string, mixed>|null
	 */
	public ?array $upload_config = null;

	/**
	 * __get
	 *
	 * Enables the use of CI super-global without having to define an extra variable.
	 *
	 * @access	public
	 * @param	$var
	 * @return	mixed
	 */
	public function __get($var)
	{
		return get_instance()->$var;
	}

	protected $model = 'attachment';
	protected $model_path = 'attachment';

	protected $public_path = 'media/files';
	protected $private_path = 's3/media/files'; //aws
	// protected $private_path = 'private/media/files';//local

	/**
	 * Set the attachment model/path, deriving the short model name from it.
	 *
	 * @param string $model_path Model path (e.g. `attachment` or `foo/attachment`); the short model name is the segment after the last slash.
	 * @return void
	 */
	public function set_model($model_path)
	{
		$this->model_path = $model_path;

		if (strpos($model_path, '/') !== false) {
			$parts = explode('/', $model_path);
			$this->model =  end($parts);
		} else {
			$this->model = $model_path;
		}
	}

	/**
	 * Fetch attachments matching a model name and hash.
	 *
	 * @param string $model_name Owning model name stored in `model_name`.
	 * @param string $hash Model hash stored in `model_hash`.
	 * @return array<string, mixed>|null Matching attachment row, or null if none found.
	 */
	public function get_by($model_name, $hash)
	{
		$this->load->model($this->model_path);
		return $this->{$this->model}->get_where(['model_hash' => $hash, 'model_name' => $model_name]);
	}

	/**
	 * Update attachments matching a model name and hash.
	 *
	 * @param array<string, mixed> $data Column => value pairs to update.
	 * @param string $model_name Owning model name stored in `model_name`.
	 * @param string $hash Model hash stored in `model_hash`.
	 * @return bool True on success.
	 */
	public function update_by($data, $model_name, $hash)
	{
		return $this->{$this->model}->update_where($data, ['model_hash' => $hash, 'model_name' => $model_name]);
	}

	/**
	 * Fetch attachments matching an arbitrary where clause.
	 *
	 * @param array<string, mixed> $where Column => value WHERE conditions.
	 * @return array<string, mixed>|null Matching attachment row, or null if none found.
	 */
	public function get_where($where)
	{
		$this->load->model($this->model_path);
		return $this->{$this->model}->get_where($where);
	}

	/**
	 * Update a single attachment by its primary key.
	 *
	 * @param array<string, mixed> $data Column => value pairs to update.
	 * @param int|string $attachment_id Primary key of the attachment to update.
	 * @return bool True on success.
	 */
	public function update_by_id($data, $attachment_id)
	{
		return $this->{$this->model}->update($data, $attachment_id);
	}

	/**
	 * Upload a request file as an attachment and record it in the model.
	 *
	 * @param string|null $field_name Name of the multipart form field carrying the file. Untyped at runtime; validated against null before use.
	 * @param string|null $module Owning model name, stored in `model_name` and used in the storage path. Untyped at runtime; validated against null before use.
	 * @param string|null $hash Model hash, stored in `model_hash` and used in the storage path. Untyped at runtime; validated against null before use.
	 * @param string $desired_filename Stored file name built by the server, never request input; empty to auto-generate. Refused when the upload library refuses it or it is over 89 characters (the `file_name` column less `?v=`).
	 * @param bool $return_data When true return the full attachment data array; when false return only the stored file URL.
	 * @param bool $public_file When true store under the public path; when false under the private (S3) path.
	 * @param array<string, mixed> $extra_data Extra column => value pairs merged into the inserted row.
	 * @param ?string $error Reason for a failure; set whenever null is returned.
	 * @return string|array<string, mixed>|null Stored file URL, or the full attachment data array when $return_data is true; null on failure, reason in `$error`.
	 * @throws InvalidArgumentException When field_name, module or hash is missing.
	 */
	public function upload_file($field_name, $module, $hash, $desired_filename = '', $return_data = false, $public_file = true, $extra_data = [], ?string &$error = null)
	{
		$this->load->model($this->model_path);

		// Basic parameter validation
		if (!isset($field_name) || !isset($module) || !isset($hash)) {
			throw new InvalidArgumentException('Invalid parameters: field_name, module and hash are required.');
		}

		$this->load->library('upload_lib');

		return $this->store_attachment(
			store: fn (string $relative_path, ?string &$store_error): ?array => $this->upload_lib->upload_file(
				$relative_path,
				$desired_filename,
				$field_name,
				$this->upload_config, // upload_config
				true, // encrypt_name
				$store_error
			),
			module: $module,
			hash: $hash,
			desired_filename: $desired_filename,
			return_data: $return_data,
			public_file: $public_file,
			extra_data: $extra_data,
			error: $error
		);
	}

	/**
	 * Store a raw data blob as an attachment and record it in the model.
	 *
	 * @param string|null $data Raw file contents to store. Untyped at runtime; validated against null before use.
	 * @param string|null $module Owning model name, stored in `model_name` and used in the storage path. Untyped at runtime; validated against null before use.
	 * @param string|null $hash Model hash, stored in `model_hash` and used in the storage path. Untyped at runtime; validated against null before use.
	 * @param string $desired_filename Stored file name built by the server, never request input; empty to auto-generate. Refused when the upload library refuses it or it is over 89 characters (the `file_name` column less `?v=`).
	 * @param bool $return_data When true return the full attachment data array; when false return only the stored file URL.
	 * @param bool $public_file When true store under the public path; when false under the private (S3) path.
	 * @param array<string, mixed> $extra_data Extra column => value pairs merged into the inserted row.
	 * @param ?string $error Reason for a failure; set whenever null is returned.
	 * @return string|array<string, mixed>|null Stored file URL, or the full attachment data array when $return_data is true; null on failure, reason in `$error`.
	 * @throws InvalidArgumentException When data, module or hash is missing.
	 */
	public function put_file($data, $module, $hash, $desired_filename = '', $return_data = false, $public_file = true, $extra_data = [], ?string &$error = null)
	{
		$this->load->model($this->model_path);

		// Basic parameter validation
		if (!isset($data) || !isset($module) || !isset($hash)) {
			throw new InvalidArgumentException('Invalid parameters: data, module and hash are required.');
		}

		$this->load->library('upload_lib');

		return $this->store_attachment(
			store: fn (string $relative_path, ?string &$store_error): ?array => $this->upload_lib->put_file(
				$relative_path,
				$desired_filename,
				$data,
				$store_error
			),
			module: $module,
			hash: $hash,
			desired_filename: $desired_filename,
			return_data: $return_data,
			public_file: $public_file,
			extra_data: $extra_data,
			error: $error
		);
	}

	/**
	 * Stores the file through `$store`, then its row; a failure after the file is stored removes it.
	 *
	 * The helpers throw; this is the one place that turns a throwable into `null` and `$error`.
	 *
	 * @param Closure(string, ?string &): ?array<string, mixed> $store Runs the upload for a relative path; `null` with its reason in the second argument on failure.
	 * @param array<string, mixed> $extra_data
	 * @return string|array<string, mixed>|null
	 */
	private function store_attachment(Closure $store, string $module, string $hash, ?string $desired_filename, bool $return_data, bool $public_file, array $extra_data, ?string &$error): string|array|null
	{
		$error = null;
		$upload_result = null;

		try {
			$this->assert_desired_name_fits($desired_filename);

			$relative_path = $public_file ? "{$this->public_path}/$module/$hash/" : "{$this->private_path}/$module/$hash/";

			$upload_result = $store($relative_path, $error);
			if ($upload_result === null) {
				$error ??= 'File upload failed.';

				return null;
			}

			// Prepare data for database insertion using the upload results
			$result_data = [
				'title' => $upload_result['client_name'], // Original filename from client
				'file_name' => $upload_result['file_name'],
				'full_path' => $upload_result['file_url'], // This should be the relative path + filename
				'type' => $upload_result['file_type'],
				'model_hash' => $hash,
				'model_name' => $module,
			];

			$attachment_id = $this->save_row(array_merge($result_data, $extra_data));

			if (!$return_data) {
				return $result_data['full_path'];
			}

			$result_data['attachment_id'] = $attachment_id;

			return $result_data;
		} catch (Throwable $e) {
			mgr_process_exception($e, 'MGR_Attachment_lib');
			$this->discard_stored_file($upload_result);

			// Only the name limit is the caller's to read; any other message may carry internals.
			$error = $e instanceof InvalidArgumentException ? $e->getMessage() : 'The attachment could not be saved.';

			return null;
		}
	}

	/**
	 * @throws InvalidArgumentException When a desired name leaves no room for the `?v=` suffix in `file_name`.
	 */
	private function assert_desired_name_fits(?string $desired_filename): void
	{
		$limit = $this->file_name_max_length - self::VERSION_SUFFIX_LENGTH;

		if ($desired_filename !== null && mb_strlen($desired_filename) > $limit) {
			throw new InvalidArgumentException("MGR_Attachment_lib: desired file name is longer than {$limit} characters.");
		}
	}

	/** Cuts a title to its column, stem first so the extension survives; invalid UTF-8 bytes are dropped. */
	private function fit_title(string $title): string
	{
		$title = mb_scrub($title);

		if (mb_strlen($title) <= $this->title_max_length) {
			return $title;
		}

		$extension = pathinfo($title, PATHINFO_EXTENSION);
		$suffix = $extension === '' ? '' : ".{$extension}";

		if ($suffix === '' || mb_strlen($suffix) >= $this->title_max_length) {
			return mb_substr($title, 0, $this->title_max_length);
		}

		return mb_substr($title, 0, $this->title_max_length - mb_strlen($suffix)) . $suffix;
	}

	/**
	 * Inserts the attachment row: title fitted, file_name checked against its column.
	 *
	 * @param array<string, mixed> $row
	 * @return int|string The new row id.
	 * @throws RuntimeException When the row is not saved.
	 */
	private function save_row(array $row): int|string
	{
		if (is_string($row['title'] ?? null)) {
			$row['title'] = $this->fit_title($row['title']);
		}

		$name_length = mb_strlen((string) ($row['file_name'] ?? ''));
		$reason = "file_name is {$name_length} characters, the limit is {$this->file_name_max_length}";

		if ($name_length <= $this->file_name_max_length) {
			$attachment_id = $this->{$this->model}->insert($row);

			if (is_int($attachment_id) || is_string($attachment_id)) {
				return $attachment_id;
			}

			$reason = 'insert failed';
		}

		throw new RuntimeException("MGR_Attachment_lib: the attachment row was not saved ({$reason}), stored file at " . ($row['full_path'] ?? ''));
	}

	/**
	 * Removes the file a call stored locally; an `s3/…` object stays (no delete method yet).
	 *
	 * @param array<string, mixed>|null $upload_result
	 */
	private function discard_stored_file(?array $upload_result): void
	{
		if ($upload_result === null || mgr_is_s3_path((string) $upload_result['file_url'])) {
			return;
		}

		try {
			if (is_file($upload_result['file_path'])) {
				unlink($upload_result['file_path']);
			}
		} catch (Throwable $e) {
			mgr_process_exception($e, 'MGR_Attachment_lib: removing the file of an unsaved row');
		}
	}

	/**
	 * Generate a hash for the attachment model.
	 *
	 * @return string A 13-character hash.
	 */
	public function get_hash()
	{
		$this->load->model($this->model_path);

		return $this->{$this->model}->get_hash(13);
	}

	/**
	 * Generate a hash guaranteed unique within the attachment model.
	 *
	 * @return string|null A 13-character hash unique within the model, or null if one could not be generated.
	 */
	public function get_unique_hash()
	{
		$this->load->model($this->model_path);

		return $this->{$this->model}->get_unique_hash(13);
	}
}
