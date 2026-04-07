<?php

class RDSMLogFileHelper {

	/**
	 * Escreve no arquivo de log, garantindo a criação do diretório, controle de concorrência e limite de tamanho.
	 *
	 * @param string $value O valor a ser escrito no log.
	 * @return bool Retorna true se a escrita foi bem-sucedida, false caso contrário.
	 */
	public static function write_to_log_file($value) {
		// Caminho completo do arquivo de log
		$file_path = trailingslashit(RDSM_LOG_FILE_PATH) . get_option('rdsm_refresh_token');
		$dir_path  = dirname($file_path);

		// Garante que o diretório exista
		if (!is_dir($dir_path)) {
			if (!wp_mkdir_p($dir_path)) {
				if (defined('WP_DEBUG') && WP_DEBUG) {
					error_log("Falha ao criar diretório de log: {$dir_path}");
				}
				return false;
			}
		}

		// Formata o conteúdo com timestamp
		date_default_timezone_set('America/Sao_Paulo');
		$time = date("F jS Y, H:i P");
		$log  = "#$time\n";

		$lines = explode("\n", trim($value));
		foreach ($lines as $line) {
			$log .= trim($line) . "\n";
		}
		$log .= "\n";

		// Escreve com segurança no arquivo (append + lock)
		$fp = fopen($file_path, 'a');
		if ($fp) {
			if (flock($fp, LOCK_EX)) {
				fwrite($fp, $log);
				flock($fp, LOCK_UN);
			}
			fclose($fp);
		
			// Aplica limite de tamanho após a escrita
			self::limit_log_file($file_path);
			return true;
		} else {
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log("Falha ao abrir arquivo de log para escrita: {$file_path}");
			}
			return false;
		}
	}
	
	/**
	 * Obtém o conteúdo do arquivo de log.
	 *
	 * @return array Retorna o conteúdo do arquivo de log como um array de linhas, ou array vazio se o arquivo não existir.
	 */
	public static function get_log_file() {
		$file_path = trailingslashit(RDSM_LOG_FILE_PATH) . get_option('rdsm_refresh_token');

		if (!file_exists($file_path) || !is_readable($file_path)) {
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log("Arquivo de log não encontrado ou não legível: {$file_path}");
			}
			return [];
		}

		// Lê o conteúdo do arquivo e retorna como array de linhas
		return file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	}

	/**
	 * Verifica se o arquivo de log contém erros.
	 *
	 * @return bool Retorna true se o arquivo de log contém a palavra "error", false caso contrário.
	 */
	public static function has_error() {
		$file_path = trailingslashit(RDSM_LOG_FILE_PATH) . get_option('rdsm_refresh_token');
	
		if (!file_exists($file_path) || !is_readable($file_path)) {
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log("Arquivo de log não encontrado ou não legível: {$file_path}");
			}
			return false; // Não encontrou arquivo ou não pode ler, assume sem erros
		}
	
		// Lê todo o conteúdo do arquivo
		$file_content = file_get_contents($file_path);
	
		if ($file_content === false) {
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log("Erro ao ler arquivo de log: {$file_path}");
			}
			return false;
		}
	
		// Verifica se a palavra "error" (case-insensitive) está presente no conteúdo
		return (stripos($file_content, 'errors') !== false);
	}
	
	/**
	 * Filtra o log por tipo: all, errors ou success
	 *
	 * @param string $filter Tipo de filtro ('all', 'errors', 'success')
	 * @return array Linhas filtradas do log
	 */
	public static function get_filtered_logs($filter = 'all') {
		$lines = self::get_log_file();
		$blocks = [];
		$current_block = [];

		foreach ($lines as $line) {
			if (strpos($line, '#') === 0) {
				// Novo bloco
				if (!empty($current_block)) {
					$blocks[] = $current_block;
				}
				$current_block = [$line];
			} else {
				$current_block[] = $line;
			}

		}
		// Último bloco
		if (!empty($current_block)) {
			$blocks[] = $current_block;
		}

		// Inverte os blocos antes de aplicar o filtro
		$blocks = array_reverse($blocks);

		// Filtra blocos e junta as linhas
		$filtered = [];
		foreach ($blocks as $block) {
			if (self::should_include_block($block, $filter)) {
				$filtered = array_merge($filtered, $block);
			}
		}

		return $filtered;
	}

	/**
	 * Determina se o bloco deve ser incluído no filtro
	 *
	 * @param array $block Bloco de linhas
	 * @param string $filter Filtro aplicado
	 * @return bool
	 */
	private static function should_include_block(array $block, string $filter): bool {
		$joined = implode("\n", $block);
		switch ($filter) {
			case 'errors':
				return stripos($joined, '"errors"') !== false;
			case 'success':
				return stripos($joined, '"errors"') === false;
			default:
				return true;
		}
	}
	
	/**
	 * Limita o tamanho do arquivo de log, removendo as linhas mais antigas se necessário
	 * @param string $file_path Caminho do arquivo de log.
	 * @return void
	 */
	private static function limit_log_file($file_path) {
		$max_lines = defined('RDSM_LOG_FILE_LIMIT') ? RDSM_LOG_FILE_LIMIT : 10000;

		if (!file_exists($file_path) || !is_readable($file_path)) {
			return;
		}

		$lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

		if (count($lines) > $max_lines) {
			$lines = array_slice($lines, -$max_lines); // pega as últimas linhas
			file_put_contents($file_path, implode("\n", $lines));
		}
	}

	/**
	 * Limpa o arquivo de log, criando um novo arquivo vazio.
	 *
	 * @return bool Retorna true se a limpeza foi bem-sucedida, false caso contrário.
	 */
	public static function clear_log_file() {
		$file_path = trailingslashit(RDSM_LOG_FILE_PATH) . get_option('rdsm_refresh_token');
	
		// Garante que o diretório exista
		$dir = dirname($file_path);
		if (!is_dir($dir)) {
			wp_mkdir_p($dir);
		}
	
		// Tenta limpar o arquivo (cria vazio se não existir)
		$result = file_put_contents($file_path, '');
	
		// Log de erro em debug
		if ($result === false && defined('WP_DEBUG') && WP_DEBUG) {
			error_log("Falha ao limpar o arquivo de log: {$file_path}");
		}
	
		return $result !== false;
	}
	
}
