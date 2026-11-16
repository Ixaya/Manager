<?php

defined('BASEPATH') or exit('No direct script access allowed');

class MGR_Image_process_lib
{
	/** Detected types treated as raster images: the ones convert_image() decodes and the ones served inline. */
	protected const array RASTER_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'];

	/** Largest width × height fits_pixel_ceiling() accepts. */
	protected const int MAX_IMAGE_PIXELS = 16_777_216;

	/** Detected types a thumbnail is built for. */
	protected const array THUMBNAIL_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

	/** Targets convert_image() re-encodes to, with the type each is stored as. */
	protected const array CONVERT_TARGETS = ['jpg' => 'image/jpeg', 'webp' => 'image/webp'];

	/** True for a detected type served inline as an image. */
	public function is_raster_type(string $detected_type): bool
	{
		return in_array($detected_type, self::RASTER_TYPES, true);
	}

	/** True for a `$convert_to` key convert_image() accepts (`jpg`, `webp`). */
	public function is_convert_target(string $convert_to): bool
	{
		return isset(static::CONVERT_TARGETS[$convert_to]);
	}

	/** The detected type a `$convert_to` key is stored as; the key must pass is_convert_target(). */
	public function convert_target_type(string $convert_to): string
	{
		return static::CONVERT_TARGETS[$convert_to];
	}

	/** True for a detected type a thumbnail is built for. */
	public function is_thumbnail_type(string $detected_type): bool
	{
		return in_array($detected_type, static::THUMBNAIL_TYPES, true);
	}

	/** True when the detected type is a raster type and this stack's GD has its decoder. */
	public function has_image_decoder(string $detected_type): bool
	{
		return in_array($detected_type, self::RASTER_TYPES, true)
			&& function_exists('imagecreatefrom' . substr($detected_type, strlen('image/')));
	}

	/** True when this stack's GD has the encoder for a CONVERT_TARGETS key (imagejpeg, imagewebp). */
	public function has_image_encoder(string $convert_to): bool
	{
		return function_exists('image' . substr(static::CONVERT_TARGETS[$convert_to], strlen('image/')));
	}

	/** False over MAX_IMAGE_PIXELS, or when the dimensions cannot be read. */
	public function fits_pixel_ceiling(string $file_path): bool
	{
		$image_size = getimagesize($file_path);

		return $image_size !== false && $image_size[0] * $image_size[1] <= static::MAX_IMAGE_PIXELS;
	}

	/**
	 * Re-encodes a raster image to `$convert_to`; metadata is dropped and a JPEG's EXIF orientation applied when `exif` is loaded.
	 * JPEG flattens transparency onto white; WebP keeps it.
	 *
	 * @throws RuntimeException When GD cannot decode the source or the encoder fails.
	 * @throws InvalidArgumentException On an unknown target.
	 */
	public function convert_image(string $source_path, string $detected_type, string $target_path, string $convert_to): void
	{
		$input_image = imagecreatefromstring(file_get_contents($source_path));
		if ($input_image === false) {
			throw new RuntimeException("MGR_Image_process_lib: cannot decode the {$detected_type} image.");
		}

		$input_image = $this->apply_exif_orientation(image: $input_image, source_path: $source_path, detected_type: $detected_type);

		$written = match ($convert_to) {
			'jpg' => imagejpeg($this->flatten_on_white($input_image), $target_path),
			'webp' => $this->encode_webp(image: $input_image, target_path: $target_path),
			default => throw new InvalidArgumentException("MGR_Image_process_lib: unknown conversion target '{$convert_to}'."),
		};

		if (!$written) {
			throw new RuntimeException("MGR_Image_process_lib: the {$convert_to} encoder failed.");
		}
	}

	/** A truecolor copy of `$image` drawn over a white background, for targets without alpha. */
	protected function flatten_on_white(GdImage $image): GdImage
	{
		// Read from the decoded image: an EXIF quarter turn swaps the dimensions getimagesize() reports.
		$width = imagesx($image);
		$height = imagesy($image);

		$output_image = imagecreatetruecolor($width, $height);
		imagefilledrectangle($output_image, 0, 0, $width, $height, imagecolorallocate($output_image, 255, 255, 255));
		imagecopy($output_image, $image, 0, 0, 0, 0, $width, $height);

		return $output_image;
	}

	protected function encode_webp(GdImage $image, string $target_path): bool
	{
		// GD's WebP encoder refuses palette images (GIF, 8-bit PNG); the conversion keeps their transparency as alpha.
		if (!imagepalettetotruecolor($image)) {
			return false;
		}

		imagesavealpha($image, true);

		return imagewebp($image, $target_path);
	}

	/** Rotates and mirrors a decoded JPEG upright per its EXIF Orientation tag; any other type comes back as given. */
	protected function apply_exif_orientation(GdImage $image, string $source_path, string $detected_type): GdImage
	{
		if ($detected_type !== 'image/jpeg' || !function_exists('exif_read_data')) {
			return $image;
		}

		$exif_data = exif_read_data($source_path);
		$orientation = is_array($exif_data) ? (int) ($exif_data['Orientation'] ?? 1) : 1;

		// imagerotate() turns counterclockwise; the rotation runs before the mirror.
		[$angle, $flip_mode] = match ($orientation) {
			2 => [0, IMG_FLIP_HORIZONTAL],
			3 => [180, null],
			4 => [0, IMG_FLIP_VERTICAL],
			5 => [-90, IMG_FLIP_HORIZONTAL],
			6 => [-90, null],
			7 => [90, IMG_FLIP_HORIZONTAL],
			8 => [90, null],
			default => [0, null],
		};

		if ($angle !== 0) {
			$rotated_image = imagerotate($image, $angle, 0);
			if ($rotated_image === false) {
				throw new RuntimeException("MGR_Image_process_lib: cannot apply EXIF orientation {$orientation}.");
			}

			$image = $rotated_image;
		}

		if ($flip_mode !== null && !imageflip($image, $flip_mode)) {
			throw new RuntimeException("MGR_Image_process_lib: cannot apply EXIF orientation {$orientation}.");
		}

		return $image;
	}
}
