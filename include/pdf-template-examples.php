<?php
/**
 * PDF Template System Usage Examples
 *
 * This file demonstrates how to use the PDF template system
 * in your WordPress theme or plugin.
 *
 * @package WordPress Plugin Template/Examples
 */

// Example 1: Generate a PDF link in a template
function get_pdf_download_link( $template_id, $label = 'Download PDF' ) {
	if ( ! wp_verify_nonce( wp_create_nonce( 'demo_pdf_render' ), 'demo_pdf_render' ) ) {
		return '';
	}

	$nonce = wp_create_nonce( 'demo_pdf_render' );
	$pdf_url = add_query_arg(
		[
			'demo_pdf' => sanitize_text_field( $template_id ),
			'nonce'    => $nonce,
		],
		home_url( '/' )
	);

	return sprintf(
		'<a href="%s" class="button button-primary">%s</a>',
		esc_url( $pdf_url ),
		esc_html( $label )
	);
}

// Example 2: Display PDF links in admin
function render_pdf_download_buttons() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$templates = [
		'example'  => 'Download Example PDF',
		'invoice'  => 'Download Invoice PDF',
		'simple'   => 'Download Simple PDF',
	];

	echo '<div class="pdf-download-buttons">';
	foreach ( $templates as $template_id => $label ) {
		echo get_pdf_download_link( $template_id, $label ); // phpcs:ignore
		echo ' ';
	}
	echo '</div>';
}

// Example 3: Generate PDF programmatically
function generate_pdf_content( $template_id ) {
	// Load dependencies
	if ( ! class_exists( 'PdfRegistry' ) ) {
		require_once plugin_dir_path( __FILE__ ) . 'include/class-pdf-registry.php';
	}

	try {
		$pdf = PdfRegistry::create( $template_id );

		if ( ! $pdf ) {
			return new WP_Error( 'pdf_not_found', 'PDF template not found' );
		}

		return $pdf;
	} catch ( Exception $e ) {
		return new WP_Error( 'pdf_error', $e->getMessage() );
	}
}

// Example 4: Shortcode for PDF downloads
function register_pdf_shortcode() {
	add_shortcode( 'pdf_download', 'pdf_download_shortcode' );
}

function pdf_download_shortcode( $atts ) {
	$atts = shortcode_atts(
		[
			'template' => 'example',
			'label'    => 'Download PDF',
		],
		$atts,
		'pdf_download'
	);

	return get_pdf_download_link( $atts['template'], $atts['label'] );
}

add_action( 'init', 'register_pdf_shortcode' );

// Example 5: Display PDF in admin page
function render_admin_pdf_page() {
	?>
	<div class="wrap">
		<h1>PDF Template Examples</h1>
		<p>Click the buttons below to download example PDFs:</p>
		<?php render_pdf_download_buttons(); ?>
		<hr />
		<h2>Available Templates</h2>
		<table class="widefat fixed">
			<thead>
				<tr>
					<th>Template ID</th>
					<th>Description</th>
					<th>Action</th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><code>example</code></td>
					<td>Simple example template with header and footer</td>
					<td><?php echo get_pdf_download_link( 'example', 'Download' ); // phpcs:ignore ?></td>
				</tr>
				<tr>
					<td><code>invoice</code></td>
					<td>Invoice template with business details</td>
					<td><?php echo get_pdf_download_link( 'invoice', 'Download' ); // phpcs:ignore ?></td>
				</tr>
				<tr>
					<td><code>simple</code></td>
					<td>Minimal template without header/footer</td>
					<td><?php echo get_pdf_download_link( 'simple', 'Download' ); // phpcs:ignore ?></td>
				</tr>
			</tbody>
		</table>
	</div>
	<?php
}

// Example 6: Add admin menu
function add_pdf_examples_admin_menu() {
	add_menu_page(
		'PDF Examples',
		'PDF Examples',
		'manage_options',
		'pdf-examples',
		'render_admin_pdf_page',
		'dashicons-document-alt',
		25
	);
}

// Uncomment to enable admin menu:
// add_action( 'admin_menu', 'add_pdf_examples_admin_menu' );

// Example 7: Use in theme template
// In your theme template file (e.g., template-pdf-showcase.php):
/*
<?php
get_header();
?>

<div class="container">
    <h1><?php the_title(); ?></h1>
    
    <h2>Download Sample PDFs</h2>
    <div class="pdf-downloads">
        <?php
        $templates = [
            'example' => 'Example Document',
            'invoice' => 'Sample Invoice',
            'simple' => 'Simple Document',
        ];
        
        foreach ( $templates as $id => $title ) {
            echo '<div class="pdf-item">';
            echo '<h3>' . esc_html( $title ) . '</h3>';
            echo get_pdf_download_link( $id, 'Download PDF' ); // phpcs:ignore
            echo '</div>';
        }
        ?>
    </div>
</div>

<?php
get_footer();
*/

// Example 8: Custom data with PDF template
function generate_invoice_pdf( $invoice_data ) {
	if ( ! class_exists( 'PdfInvoice' ) ) {
		require_once plugin_dir_path( __FILE__ ) . 'include/class-pdf-invoice.php';
	}

	try {
		$pdf = new PdfInvoice();
		$pdf->setInvoiceNumber( $invoice_data['number'] ?? 'INV-001' );
		$pdf->setDocTitle( 'Invoice #' . ( $invoice_data['number'] ?? 'INV-001' ) );

		return $pdf;
	} catch ( Exception $e ) {
		return new WP_Error( 'pdf_error', $e->getMessage() );
	}
}

// Example 9: AJAX handler for PDF generation
function register_pdf_ajax() {
	add_action( 'wp_ajax_generate_pdf', 'pdf_ajax_handler' );
}

function pdf_ajax_handler() {
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'pdf_nonce' ) ) {
		wp_send_json_error( [ 'message' => 'Security check failed' ] );
	}

	$template_id = isset( $_POST['template'] ) ? sanitize_text_field( wp_unslash( $_POST['template'] ) ) : 'example';

	if ( ! class_exists( 'PdfRegistry' ) ) {
		require_once plugin_dir_path( __FILE__ ) . 'include/class-pdf-registry.php';
	}

	try {
		if ( ! PdfRegistry::exists( $template_id ) ) {
			wp_send_json_error( [ 'message' => 'Template not found' ] );
		}

		$pdf_url = add_query_arg(
			[
				'demo_pdf' => $template_id,
				'nonce'    => wp_create_nonce( 'demo_pdf_render' ),
			],
			home_url( '/' )
		);

		wp_send_json_success( [ 'url' => $pdf_url ] );
	} catch ( Exception $e ) {
		wp_send_json_error( [ 'message' => $e->getMessage() ] );
	}
}

add_action( 'init', 'register_pdf_ajax' );

// Example 10: Enqueue JavaScript for AJAX PDF generation
function enqueue_pdf_scripts() {
	wp_enqueue_script( 'pdf-ajax', plugin_dir_url( __FILE__ ) . 'assets/js/pdf-ajax.js', [ 'jquery' ], '1.0', true );
	wp_localize_script(
		'pdf-ajax',
		'pdfAjax',
		[
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'pdf_nonce' ),
		]
	);
}

add_action( 'wp_enqueue_scripts', 'enqueue_pdf_scripts' );

?>
