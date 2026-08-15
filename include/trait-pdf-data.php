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
 *       public function __construct() {
 *           $this->initializeUrlData();  // Load $_GET parameters
 *           $this->setFolderName('bfv_erlaubnisschein');  // Set storage folder name
 *       }
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
 *           $this->createStorageFolder();  // Ensure storage folder exists
 *       }
 *
 *       protected function render(): void {
 *           $name = $this->getAddress('name');
 *           $color = $this->getOption('accent_color', '#000000');
 *           $page = $this->getUrl('page', 1);
 *           $storagePath = $this->getStoragePath();
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

	/**
	 * URL / query string parameters.
	 *
	 * @var array<string, mixed>
	 */
	private array $urldata = [];

	/**
	 * PDF storage folder name (appended to wp_upload_dir()['basedir']).
	 * Each template must set this via setFolderName().
	 *
	 * @var string
	 */
	private string $folderName = '';

	/**
	 * PDF file name (with full path).
	 *
	 * @var string
	 */
	private string $fileName = '';


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

	/**
	 * Replace the entire urldata array.
	 *
	 * @param array<string, mixed> $urldata
	 *
	 * @return void
	 */
	public function setUrldata(array $urldata): void {
		$this->urldata = $urldata;
	}

	/**
	 * Set the PDF storage folder name.
	 *
	 * @param string $folderName The folder name (appended to wp_upload_dir()['basedir']).
	 *
	 * @return void
	 */
	public function setFolderName(string $folderName): void {
		$this->folderName = $folderName;
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

	/**
	 * Set a single urldata value.
	 *
	 * @param string $key
	 * @param mixed  $value
	 *
	 * @return void
	 */
	public function setUrl(string $key, mixed $value): void {
		$this->urldata[$key] = $value;
	}

	/**
	 * Set the absolute file name for the PDF including the folder name.
	 *
	 * Combines the storage folder path with the given file name.
	 *
	 * @param string $fileName The file name (without path).
	 *
	 * @return void
	 */
	public function setFileName(string $fileName): void {
		$this->fileName = $fileName;
	}

	/**
	 * Get the base file name for the PDF.
	 *
	 * @return string
	 */
	public function getFileName(): string {
		return $this->fileName;
	}

	/**
	 * Get the absolute file name for the PDF.
	 *
	 * @return string The absolute file name (with full path), or empty string if not set.
	 */
	public function getFileNameAbs(): string {
		$storagePath = $this->getStoragePath();
		if ($storagePath !== '') {
			return $storagePath . '/' . $this->fileName;
		} else {
			return $this->fileName;
		}
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

	/**
	 * Get a single urldata value.
	 *
	 * @param string $key
	 * @param mixed  $default Returned when the key is not set.
	 *
	 * @return mixed
	 */
	public function getUrl(string $key, mixed $default = null): mixed {
		return $this->urldata[$key] ?? $default;
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

	/**
	 * Get the full urldata array.
	 *
	 * @return array<string, mixed>
	 */
	public function getAllUrldata(): array {
		return $this->urldata;
	}

	// -----------------------------------------------------------------------
	// Initialization
	// -----------------------------------------------------------------------

	/**
	 * Initialize URL data from $_GET or $_POST.
	 *
	 * Call this in your class constructor or during initialization.
	 *
	 * @param array<string, mixed>|null $source Data source (defaults to $_GET)
	 *
	 * @return void
	 */
	public function initializeUrlData(?array $source = null): void {
		$source = $source ?? $_GET;
		$this->setUrldata($source);
	}

	// -----------------------------------------------------------------------
	// Storage
	// -----------------------------------------------------------------------

	/**
	 * Get the full storage path for PDF files.
	 *
	 * @return string The full path to the storage folder, or empty string if folder name not set.
	 */
	public function getStoragePath(): string {
		if ($this->folderName === '') {
			return '';
		}
		$uploadDir = wp_upload_dir();
		return $uploadDir['basedir'] . '/' . $this->folderName;
	}

	/**
	 * Create the PDF storage folder if it doesn't exist.
	 *
	 * Must call setFolderName() first to define the folder name.
	 * or pass a folder name as argument.
	 *
	 * @return bool True if folder exists or was created, false on error.
	 */
	public function createStorageFolder(?string $folderName = null): bool {
		if ($folderName !== null && $folderName !== '') {
			$this->setFolderName($folderName);
		}
		$path = $this->getStoragePath();
		if ($path === '') {
			return false;
		}
		if (is_dir($path)) {
			return true;
		}
		return wp_mkdir_p($path);
	}
}
