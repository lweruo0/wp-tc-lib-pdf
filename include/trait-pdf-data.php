<?php
/**
 * PDF Data Trait.
 *
 * Provides three typed data containers (options, formdata, addressdata)
 * that PDF templates use to decouple data sourcing from rendering.
 *
 * Each template that uses this trait must implement loadData() to define
 * how the three arrays are populated (e.g. from $_POST, WP options,
 * a WooCommerce order, a Quform submission, etc.).
 *
 * @package WordPress Plugin Template/Includes
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Trait PdfDataTrait.
 *
 * Usage in a template class:
 *
 *   class PdfMyTemplate extends PdfTemplate {
 *       use PdfDataTrait;
 *
 *       protected function loadData(): void {
 *           $this->setOptions([
 *               'show_logo' => true,
 *               'accent_color' => '#1a3a6b',
 *           ]);
 *           $this->setFormdata([
 *               'member_id'   => get_query_var('member_id'),
 *               'membership'  => 'standard',
 *           ]);
 *           $this->setAddressdata([
 *               'name'    => 'Max Mustermann',
 *               'street'  => 'Musterstr. 1',
 *               'zip'     => '12345',
 *               'city'    => 'Musterstadt',
 *               'country' => 'DE',
 *           ]);
 *       }
 *
 *       protected function render(): void {
 *           $name = $this->getAddress('name');
 *           $color = $this->getOption('accent_color', '#000000');
 *           // ...
 *       }
 *   }
 */
trait PdfDataTrait {
	/**
	 * General document options (layout, branding, feature flags, …).
	 *
	 * @var array<string, mixed>
	 */
	private array $options = [];

	/**
	 * Form / application data (filled-in fields from a submission or DB record).
	 *
	 * @var array<string, mixed>
	 */
	private array $formdata = [];

	/**
	 * Address / contact data (recipient or sender details).
	 *
	 * @var array<string, mixed>
	 */
	private array $addressdata = [];

	// -----------------------------------------------------------------------
	// Setters – bulk replace
	// -----------------------------------------------------------------------

	/**
	 * Replace the entire options array.
	 *
	 * @param array<string, mixed> $options
	 *
	 * @return void
	 */
	public function setOptions(array $options): void {
		$this->options = $options;
	}

	/**
	 * Replace the entire formdata array.
	 *
	 * @param array<string, mixed> $formdata
	 *
	 * @return void
	 */
	public function setFormdata(array $formdata): void {
		$this->formdata = $formdata;
	}

	/**
	 * Replace the entire addressdata array.
	 *
	 * @param array<string, mixed> $addressdata
	 *
	 * @return void
	 */
	public function setAddressdata(array $addressdata): void {
		$this->addressdata = $addressdata;
	}

	// -----------------------------------------------------------------------
	// Setters – single key
	// -----------------------------------------------------------------------

	/**
	 * Set a single option value.
	 *
	 * @param string $key
	 * @param mixed  $value
	 *
	 * @return void
	 */
	public function setOption(string $key, mixed $value): void {
		$this->options[$key] = $value;
	}

	/**
	 * Set a single formdata value.
	 *
	 * @param string $key
	 * @param mixed  $value
	 *
	 * @return void
	 */
	public function setForm(string $key, mixed $value): void {
		$this->formdata[$key] = $value;
	}

	/**
	 * Set a single addressdata value.
	 *
	 * @param string $key
	 * @param mixed  $value
	 *
	 * @return void
	 */
	public function setAddress(string $key, mixed $value): void {
		$this->addressdata[$key] = $value;
	}

	// -----------------------------------------------------------------------
	// Getters – single key with default
	// -----------------------------------------------------------------------

	/**
	 * Get a single option value.
	 *
	 * @param string $key
	 * @param mixed  $default Returned when the key is not set.
	 *
	 * @return mixed
	 */
	public function getOption(string $key, mixed $default = null): mixed {
		return $this->options[$key] ?? $default;
	}

	/**
	 * Get a single formdata value.
	 *
	 * @param string $key
	 * @param mixed  $default Returned when the key is not set.
	 *
	 * @return mixed
	 */
	public function getForm(string $key, mixed $default = null): mixed {
		return $this->formdata[$key] ?? $default;
	}

	/**
	 * Get a single addressdata value.
	 *
	 * @param string $key
	 * @param mixed  $default Returned when the key is not set.
	 *
	 * @return mixed
	 */
	public function getAddress(string $key, mixed $default = null): mixed {
		return $this->addressdata[$key] ?? $default;
	}

	// -----------------------------------------------------------------------
	// Getters – full arrays
	// -----------------------------------------------------------------------

	/**
	 * Get the full options array.
	 *
	 * @return array<string, mixed>
	 */
	public function getAllOptions(): array {
		return $this->options;
	}

	/**
	 * Get the full formdata array.
	 *
	 * @return array<string, mixed>
	 */
	public function getAllFormdata(): array {
		return $this->formdata;
	}

	/**
	 * Get the full addressdata array.
	 *
	 * @return array<string, mixed>
	 */
	public function getAllAddressdata(): array {
		return $this->addressdata;
	}
}
