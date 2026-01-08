<?php
/**
 * Reclamo Bot Tests
 *
 * Run with: ./vendor/bin/phpunit
 * Or with Docker: docker run --rm -v $(pwd):/app -w /app php:8.2-cli vendor/bin/phpunit
 */

use PHPUnit\Framework\TestCase;

class ReclamoBotTest extends TestCase
{
    private string $fixturesPath;

    protected function setUp(): void
    {
        $this->fixturesPath = __DIR__ . '/fixtures';
        if (!is_dir($this->fixturesPath)) {
            mkdir($this->fixturesPath, 0777, true);
        }
    }

    // ========================================
    // getMunicipios() Tests
    // ========================================

    public function testGetMunicipiosReturnsSortedArray(): void
    {
        $municipios = getMunicipios(__DIR__ . '/../municipios');

        $this->assertIsArray($municipios);
        $this->assertNotEmpty($municipios);

        // Check sorted alphabetically by nombre
        $nombres = array_column($municipios, 'nombre');
        $sortedNombres = $nombres;
        sort($sortedNombres, SORT_STRING);

        $this->assertEquals($sortedNombres, $nombres, 'Municipios should be sorted alphabetically');
    }

    public function testGetMunicipiosAddsFileAttribute(): void
    {
        $municipios = getMunicipios(__DIR__ . '/../municipios');

        foreach ($municipios as $m) {
            $this->assertArrayHasKey('_file', $m, 'Each municipio should have _file attribute');
            $this->assertNotEmpty($m['_file']);
        }
    }

    public function testGetMunicipiosEmptyDirectoryReturnsEmptyArray(): void
    {
        $emptyDir = $this->fixturesPath . '/empty_municipios';
        if (!is_dir($emptyDir)) {
            mkdir($emptyDir, 0777, true);
        }

        $municipios = getMunicipios($emptyDir);

        $this->assertIsArray($municipios);
        $this->assertEmpty($municipios);
    }

    public function testGetMunicipiosHandlesInvalidJson(): void
    {
        $testDir = $this->fixturesPath . '/invalid_json';
        if (!is_dir($testDir)) {
            mkdir($testDir, 0777, true);
        }

        // Create valid JSON file
        file_put_contents($testDir . '/valid.json', json_encode([
            'nombre' => 'Test Municipio',
            'provincia' => 'Test'
        ]));

        // Create invalid JSON file
        file_put_contents($testDir . '/invalid.json', '{ invalid json }');

        $municipios = getMunicipios($testDir);

        // Should only return valid one
        $this->assertCount(1, $municipios);
        $this->assertEquals('Test Municipio', $municipios[0]['nombre']);
    }

    // ========================================
    // extractSubject() Tests
    // ========================================

    public function testExtractSubjectWithAsuntoLine(): void
    {
        $complaint = "---\nAsunto: Bache peligroso en intersección\nUbicación: Calle 123\n---\n\nDe mi mayor consideración...";

        $subject = extractSubject($complaint);

        $this->assertEquals('Bache peligroso en intersección', $subject);
    }

    public function testExtractSubjectWithAsuntoLineCaseInsensitive(): void
    {
        $complaint = "asunto: Luminaria rota en esquina\n\nCarta formal...";

        $subject = extractSubject($complaint);

        $this->assertEquals('Luminaria rota en esquina', $subject);
    }

    public function testExtractSubjectFallbackFirst60Chars(): void
    {
        $complaint = "Este es un reclamo sin linea de asunto que tiene mas de sesenta caracteres en total para probar el truncamiento.";

        $subject = extractSubject($complaint);

        // Subject should be 60 chars + "..." = 63 total
        $this->assertLessThanOrEqual(63, mb_strlen($subject));
        $this->assertStringEndsWith('...', $subject);
    }

    public function testExtractSubjectShortTextNoEllipsis(): void
    {
        $complaint = "Reclamo corto sin asunto";

        $subject = extractSubject($complaint);

        $this->assertEquals('Reclamo corto sin asunto', $subject);
        $this->assertFalse(str_ends_with($subject, '...'), 'Short text should not have ellipsis');
    }

    public function testExtractSubjectCollapsesWhitespace(): void
    {
        $complaint = "Texto   con    muchos     espacios   y\n\nsaltos\nde\nlínea";

        $subject = extractSubject($complaint);

        $this->assertStringNotContainsString('  ', $subject);
        $this->assertStringNotContainsString("\n", $subject);
    }

    // ========================================
    // parseCcEmails() Tests
    // ========================================

    public function testParseCcEmailsEmpty(): void
    {
        $result = parseCcEmails('');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testParseCcEmailsSingle(): void
    {
        $result = parseCcEmails('test@example.com');

        $this->assertEquals(['test@example.com'], $result);
    }

    public function testParseCcEmailsMultiple(): void
    {
        $result = parseCcEmails('one@test.com, two@test.com, three@test.com');

        $this->assertCount(3, $result);
        $this->assertEquals('one@test.com', $result[0]);
        $this->assertEquals('two@test.com', $result[1]);
        $this->assertEquals('three@test.com', $result[2]);
    }

    public function testParseCcEmailsTrimsWhitespace(): void
    {
        $result = parseCcEmails('  one@test.com  ,  two@test.com  ');

        $this->assertEquals(['one@test.com', 'two@test.com'], $result);
    }

    // ========================================
    // formatAttachmentMessage() Tests
    // ========================================

    public function testFormatAttachmentMessageNoAttachments(): void
    {
        $msg = formatAttachmentMessage(0, 'Municipalidad de Tigre');

        $this->assertEquals('Reclamo enviado a Municipalidad de Tigre!', $msg);
    }

    public function testFormatAttachmentMessageSingular(): void
    {
        $msg = formatAttachmentMessage(1, 'Municipalidad de Tigre');

        $this->assertStringContainsString('1 foto adjunta', $msg);
        $this->assertStringNotContainsString('fotos', $msg);
    }

    public function testFormatAttachmentMessagePlural(): void
    {
        $msg = formatAttachmentMessage(3, 'Municipalidad de Tigre');

        $this->assertStringContainsString('3 fotos adjuntas', $msg);
    }

    // ========================================
    // validateCoordinates() Tests
    // ========================================

    public function testValidateCoordinatesValid(): void
    {
        $result = validateCoordinates('-34.6037', '-58.3816');

        $this->assertEquals('-34.6037', $result['lat']);
        $this->assertEquals('-58.3816', $result['lng']);
    }

    public function testValidateCoordinatesInvalidLat(): void
    {
        $result = validateCoordinates('91', '-58.3816'); // lat > 90

        $this->assertEquals('', $result['lat']);
        $this->assertEquals('-58.3816', $result['lng']);
    }

    public function testValidateCoordinatesInvalidLng(): void
    {
        $result = validateCoordinates('-34.6037', '181'); // lng > 180

        $this->assertEquals('-34.6037', $result['lat']);
        $this->assertEquals('', $result['lng']);
    }

    public function testValidateCoordinatesNonNumeric(): void
    {
        $result = validateCoordinates('not-a-number', 'also-not');

        $this->assertEquals('', $result['lat']);
        $this->assertEquals('', $result['lng']);
    }

    public function testValidateCoordinatesEmpty(): void
    {
        $result = validateCoordinates('', '');

        $this->assertEquals('', $result['lat']);
        $this->assertEquals('', $result['lng']);
    }

    public function testValidateCoordinatesBoundaryValues(): void
    {
        // Valid boundaries
        $result = validateCoordinates('90', '180');
        $this->assertEquals('90', $result['lat']);
        $this->assertEquals('180', $result['lng']);

        $result = validateCoordinates('-90', '-180');
        $this->assertEquals('-90', $result['lat']);
        $this->assertEquals('-180', $result['lng']);
    }

    // ========================================
    // resizeImage() Tests (require GD)
    // ========================================

    public function testResizeImageUnknownMimeReturnsOriginal(): void
    {
        $testFile = $this->fixturesPath . '/test.txt';
        file_put_contents($testFile, 'This is not an image');

        $result = resizeImage($testFile, 'text/plain', 1024);

        $this->assertEquals('This is not an image', $result);
    }

    /**
     * @requires extension gd
     */
    public function testResizeImageReducesQuality(): void
    {
        // Create a test JPEG image
        $testFile = $this->fixturesPath . '/test_large.jpg';
        $img = imagecreatetruecolor(1000, 1000);

        // Fill with random colors to prevent compression being too effective
        for ($i = 0; $i < 1000; $i++) {
            for ($j = 0; $j < 1000; $j++) {
                $color = imagecolorallocate($img, rand(0, 255), rand(0, 255), rand(0, 255));
                imagesetpixel($img, $i, $j, $color);
            }
        }
        imagejpeg($img, $testFile, 100);
        imagedestroy($img);

        $originalSize = filesize($testFile);
        $maxSize = (int)($originalSize * 0.5); // Request 50% size reduction

        $result = resizeImage($testFile, 'image/jpeg', $maxSize);

        $this->assertLessThanOrEqual($maxSize * 1.1, strlen($result), 'Result should be approximately within target size');
    }

    /**
     * @requires extension gd
     */
    public function testResizeImageScalesDimensions(): void
    {
        // Create a large test image
        $testFile = $this->fixturesPath . '/test_scale.jpg';
        $img = imagecreatetruecolor(2000, 2000);

        for ($i = 0; $i < 2000; $i++) {
            for ($j = 0; $j < 2000; $j++) {
                $color = imagecolorallocate($img, rand(0, 255), rand(0, 255), rand(0, 255));
                imagesetpixel($img, $i, $j, $color);
            }
        }
        imagejpeg($img, $testFile, 100);
        imagedestroy($img);

        // Request very small output to force dimension scaling
        $result = resizeImage($testFile, 'image/jpeg', 50000);

        // Verify it's a valid JPEG by checking magic bytes
        $this->assertStringStartsWith("\xFF\xD8\xFF", $result, 'Result should be valid JPEG');
    }

    /**
     * @requires extension gd
     */
    public function testResizeImageInvalidImageReturnsOriginal(): void
    {
        $testFile = $this->fixturesPath . '/fake_image.jpg';
        file_put_contents($testFile, 'This is not actually a JPEG');

        $result = resizeImage($testFile, 'image/jpeg', 1024);

        $this->assertEquals('This is not actually a JPEG', $result);
    }

    // ========================================
    // Cleanup
    // ========================================

    protected function tearDown(): void
    {
        // Clean up test fixtures
        $files = glob($this->fixturesPath . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        // Clean up subdirectories
        $dirs = ['empty_municipios', 'invalid_json'];
        foreach ($dirs as $dir) {
            $path = $this->fixturesPath . '/' . $dir;
            if (is_dir($path)) {
                $subfiles = glob($path . '/*');
                foreach ($subfiles as $f) {
                    if (is_file($f)) unlink($f);
                }
                rmdir($path);
            }
        }
    }
}
