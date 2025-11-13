<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RuntimeException;

/**
 * Команда для генерации RSA пары ключей для подписи JWT токенов.
 *
 * Генерирует RSA пару ключей (2048 бит по умолчанию) и сохраняет их в:
 * - storage/keys/jwt-{kid}-private.pem (права 0600)
 * - storage/keys/jwt-{kid}-public.pem (права 0644)
 *
 * @package App\Console\Commands
 */
class GenerateJwtKeys extends Command
{
    /**
     * Имя и сигнатура консольной команды.
     *
     * @var string
     */
    protected $signature = 'cms:jwt:keys {kid : The key ID (e.g., v1, v2)}
                                        {--bits=2048 : RSA key size in bits}
                                        {--force : Overwrite existing keys}';

    /**
     * Описание консольной команды.
     *
     * @var string
     */
    protected $description = 'Generate RSA key pair for JWT token signing';

    /**
     * Выполнить консольную команду.
     *
     * Генерирует RSA пару ключей указанного размера (минимум 2048 бит)
     * и сохраняет их в storage/keys с безопасными правами доступа.
     *
     * @return int Код возврата (0 = успех, 1 = ошибка)
     */
    public function handle(): int
    {
        $kid = $this->argument('kid');
        $bits = (int) $this->option('bits');
        $force = $this->option('force');

        // Validate key size
        if ($bits < 2048) {
            $this->error('Key size must be at least 2048 bits for security.');
            return self::FAILURE;
        }

        $keysDir = storage_path('keys');
        $privateKeyPath = "{$keysDir}/jwt-{$kid}-private.pem";
        $publicKeyPath = "{$keysDir}/jwt-{$kid}-public.pem";

        // Check if keys already exist
        if (!$force && (file_exists($privateKeyPath) || file_exists($publicKeyPath))) {
            $this->error("Keys for '{$kid}' already exist. Use --force to overwrite.");
            return self::FAILURE;
        }

        // Ensure keys directory exists
        if (!is_dir($keysDir)) {
            $this->info('Creating keys directory...');
            if (!mkdir($keysDir, 0755, true) && !is_dir($keysDir)) {
                throw new RuntimeException("Failed to create directory: {$keysDir}");
            }
        }

        $this->info("Generating {$bits}-bit RSA key pair for '{$kid}'...");

        // Generate private key
        $config = [
            'private_key_bits' => $bits,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $privateKey = openssl_pkey_new($config);
        if ($privateKey === false) {
            $this->error('Failed to generate private key: ' . openssl_error_string());
            return self::FAILURE;
        }

        // Export private key
        if (!openssl_pkey_export($privateKey, $privateKeyPem)) {
            $this->error('Failed to export private key: ' . openssl_error_string());
            return self::FAILURE;
        }

        // Extract public key
        $publicKeyDetails = openssl_pkey_get_details($privateKey);
        if ($publicKeyDetails === false) {
            $this->error('Failed to extract public key: ' . openssl_error_string());
            return self::FAILURE;
        }
        $publicKeyPem = $publicKeyDetails['key'];

        // Write private key
        if (file_put_contents($privateKeyPath, $privateKeyPem) === false) {
            $this->error("Failed to write private key to: {$privateKeyPath}");
            return self::FAILURE;
        }

        // Write public key
        if (file_put_contents($publicKeyPath, $publicKeyPem) === false) {
            $this->error("Failed to write public key to: {$publicKeyPath}");
            return self::FAILURE;
        }

        // Set secure permissions on private key (owner read/write only)
        chmod($privateKeyPath, 0600);
        chmod($publicKeyPath, 0644);

        $this->newLine();
        $this->info('✓ RSA key pair generated successfully!');
        $this->line("  Private key: {$privateKeyPath} (permissions: 0600)");
        $this->line("  Public key:  {$publicKeyPath} (permissions: 0644)");
        $this->newLine();
        $this->comment("Remember to add '{$kid}' to your config/jwt.php keys array.");

        return self::SUCCESS;
    }
}

