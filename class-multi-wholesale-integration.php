<?php
/**
 * Główna klasa integracji z hurtowniami.
 *
 * @package Multi_Hurtownie_Integration
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Klasa Multi_Hurtownie_Integration
 * 
 * Obsługuje główne funkcje integracyjne.
 */
class Multi_Hurtownie_Integration
{

	/**
	 * Konstruktor klasy.
	 */
	public function __construct()
	{
		// Dodaj obsługę funkcji AJAX
		add_action('wp_ajax_mhi_check_download_status', array($this, 'ajax_check_download_status'));
		add_action('wp_ajax_mhi_cancel_download', array($this, 'ajax_cancel_download'));
		add_action('wp_ajax_mhi_fetch_images_batch', array($this, 'ajax_fetch_images_batch'));
	}

	/**
	 * Obsługuje żądanie AJAX sprawdzenia statusu pobierania.
	 */
	public function ajax_check_download_status()
	{
		// Sprawdź żądanie AJAX, autoryzację i nonce
		check_ajax_referer('mhi-ajax-nonce', 'nonce');

		// Pobierz parametry
		$hurtownia_id = isset($_POST['hurtownia_id']) ? sanitize_text_field($_POST['hurtownia_id']) : '';

		if (empty($hurtownia_id)) {
			wp_send_json_error(array('message' => __('Brak identyfikatora hurtowni', 'multi-wholesale-integration')));
			return;
		}

		// Pobierz status pobierania dla danej hurtowni
		$status = get_option('mhi_download_status_' . $hurtownia_id, __('Brak statusu', 'multi-wholesale-integration'));

		// Pobierz informacje o pozostałych partiach
		$remaining_batches = get_option('mhi_remaining_batches_' . $hurtownia_id, 0);

		// Przygotuj odpowiedź
		$response = array(
			'status' => $status,
			'remaining_batches' => $remaining_batches,
			'hurtownia_id' => $hurtownia_id,
		);

		wp_send_json_success($response);
	}

	/**
	 * Obsługuje żądanie AJAX anulowania pobierania.
	 */
	public function ajax_cancel_download()
	{
		// Sprawdź żądanie AJAX, autoryzację i nonce
		check_ajax_referer('mhi-ajax-nonce', 'nonce');

		// Pobierz parametry
		$hurtownia_id = isset($_POST['hurtownia_id']) ? sanitize_text_field($_POST['hurtownia_id']) : '';

		if (empty($hurtownia_id)) {
			wp_send_json_error(array('message' => __('Brak identyfikatora hurtowni', 'multi-wholesale-integration')));
			return;
		}

		// Ustaw flagę anulowania
		update_option('mhi_cancel_download_' . $hurtownia_id, true);
		update_option('mhi_download_status_' . $hurtownia_id, __('Anulowanie pobierania...', 'multi-wholesale-integration'));

		// Pobierz instancję hurtowni
		$integration = $this->get_integration($hurtownia_id);
		if ($integration && method_exists($integration, 'cancel_download')) {
			$integration->cancel_download();
		}

		wp_send_json_success(array(
			'message' => __('Zlecono anulowanie pobierania', 'multi-wholesale-integration'),
			'hurtownia_id' => $hurtownia_id,
		));
	}

	/**
	 * Obsługuje żądanie AJAX pobierania partii zdjęć.
	 */
	public function ajax_fetch_images_batch()
	{
		// Sprawdź żądanie AJAX, autoryzację i nonce
		check_ajax_referer('mhi-ajax-nonce', 'nonce');

		// Pobierz parametry
		$hurtownia_id = isset($_POST['hurtownia_id']) ? sanitize_text_field($_POST['hurtownia_id']) : '';
		$batch_number = isset($_POST['batch_number']) ? intval($_POST['batch_number']) : 1;
		$img_dir = isset($_POST['img_dir']) ? sanitize_text_field($_POST['img_dir']) : '/images';

		if (empty($hurtownia_id)) {
			wp_send_json_error(array('message' => __('Brak identyfikatora hurtowni', 'multi-wholesale-integration')));
			return;
		}

		// Pobierz instancję hurtowni
		$integration = $this->get_integration($hurtownia_id);
		if (!$integration) {
			wp_send_json_error(array('message' => __('Nie znaleziono hurtowni o podanym identyfikatorze', 'multi-wholesale-integration')));
			return;
		}

		// Sprawdź, czy hurtownia obsługuje pobieranie zdjęć w partiach
		if (!method_exists($integration, 'fetch_images')) {
			wp_send_json_error(array('message' => __('Ta hurtownia nie obsługuje pobierania zdjęć w partiach', 'multi-wholesale-integration')));
			return;
		}

		// Pobierz partię zdjęć
		$files = $integration->fetch_images($batch_number, $img_dir);

		// Pobierz informacje o pozostałych partiach
		$remaining_batches = get_option('mhi_remaining_batches_' . $hurtownia_id, 0);

		// Przygotuj odpowiedź
		$response = array(
			'files' => $files,
			'hurtownia_id' => $hurtownia_id,
			'batch_number' => $batch_number,
			'remaining_batches' => $remaining_batches,
			'status' => get_option('mhi_download_status_' . $hurtownia_id, __('Brak statusu', 'multi-wholesale-integration')),
		);

		wp_send_json_success($response);
	}

	/**
	 * Pobiera instancję integracji dla danej hurtowni.
	 *
	 * @param string $hurtownia_id Identyfikator hurtowni.
	 * @return MHI_Integration_Interface|null Instancja integracji lub null.
	 */
	private function get_integration($hurtownia_id)
	{
		// Mapowanie identyfikatorów hurtowni na instancje klas integracji
		$integrations = array(
			'malfini' => new MHI_Hurtownia_1(), // Malfini (API)
			'axpol' => new MHI_Hurtownia_2(),   // AXPOL (FTP)
			'par' => new MHI_Par(),             // PAR (API)
			'inspirion' => new MHI_Hurtownia_4(), // Inspirion (FTP/SFTP)
			'macma' => new MHI_Hurtownia_5(),    // Macma (API/XML)

			// Zachowanie kompatybilności wstecznej z poprzednimi identyfikatorami
			'hurtownia_1' => new MHI_Hurtownia_1(),
			'hurtownia_2' => new MHI_Hurtownia_2(),
			'hurtownia_3' => new MHI_Par(),     // PAR (API) - poprawka mapowania
			'hurtownia_4' => new MHI_Hurtownia_4(),
			'hurtownia_5' => new MHI_Hurtownia_5(),
		);

		return isset($integrations[$hurtownia_id]) ? $integrations[$hurtownia_id] : null;
	}
}