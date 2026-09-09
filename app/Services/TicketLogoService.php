<?php

namespace App\Services;

use GdImage;
use RuntimeException;

/**
 * Convierte el logo que sube el negocio (PNG/JPG a color) en el búfer ESC/POS
 * en blanco y negro que la impresora térmica entiende: el comando `GS v 0`
 * (mapa de bits ráster). El agente local de impresión antepone estos bytes
 * tal cual al ticket de venta; no sabe nada de imágenes ni necesita librerías.
 *
 * También devuelve un PNG monocromo de vista previa para mostrar en el panel
 * de configuración exactamente lo que va a salir impreso.
 */
class TicketLogoService
{
    /** Ancho máximo del logo en puntos según el ancho de papel (columnas). */
    private const MAX_DOTS = [32 => 384, 42 => 448, 48 => 512];

    /** Alto máximo del logo en puntos (evita que un logo enorme coma papel). */
    private const MAX_HEIGHT = 240;

    /**
     * Devuelve `escpos` (base64 del búfer `GS v 0 ...` listo para la impresora)
     * y `preview_png` (PNG binario monocromo para previsualizar en el panel).
     *
     * @return array{escpos: string, preview_png: string}
     */
    public function fromImage(string $binary, int $paperWidthChars): array
    {
        $src = @imagecreatefromstring($binary);

        if (! $src instanceof GdImage) {
            throw new RuntimeException('No se pudo leer la imagen del logo. Usá un PNG o JPG válido.');
        }

        [$srcW, $srcH] = [imagesx($src), imagesy($src)];
        $maxWidth = self::MAX_DOTS[$paperWidthChars] ?? 512;

        $scale = min(1.0, $maxWidth / $srcW, self::MAX_HEIGHT / $srcH);
        $width = max(8, (int) round($srcW * $scale));
        $height = max(1, (int) round($srcH * $scale));
        // El ancho de los datos ráster se cuenta en bytes: redondeamos a un
        // múltiplo de 8 puntos y rellenamos con blanco a la derecha.
        $width = (int) (ceil($width / 8) * 8);

        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, true);
        imagefilledrectangle($canvas, 0, 0, $width, $height, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $src, 0, 0, 0, 0, $width, $height, $srcW, $srcH);
        imagedestroy($src);

        $mono = $this->dither($canvas, $width, $height);
        imagedestroy($canvas);

        return [
            'escpos' => base64_encode($this->rasterCommand($mono, $width, $height)),
            'preview_png' => $this->previewPng($mono, $width, $height),
        ];
    }

    /**
     * Difuminado Floyd-Steinberg: convierte la imagen a 1 bit por píxel
     * repartiendo el error de cuantización a los vecinos, así un logo con
     * degradados o fotos se ve razonable en una térmica de dos tonos.
     *
     * @return array<int, array<int, bool>> $mono[y][x] = true si el punto es negro
     */
    private function dither(GdImage $img, int $width, int $height): array
    {
        $lum = [];

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgba = imagecolorat($img, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F; // 0 = opaco ... 127 = transparente
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;
                $value = 0.299 * $r + 0.587 * $g + 0.114 * $b;
                // Lo transparente se trata como papel blanco.
                $lum[$y][$x] = $value + ($alpha / 127) * (255 - $value);
            }
        }

        $mono = [];

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $old = $lum[$y][$x];
                $new = $old < 128 ? 0.0 : 255.0;
                $mono[$y][$x] = $new === 0.0;
                $error = $old - $new;

                if ($x + 1 < $width) {
                    $lum[$y][$x + 1] += $error * 7 / 16;
                }

                if ($y + 1 < $height) {
                    if ($x - 1 >= 0) {
                        $lum[$y + 1][$x - 1] += $error * 3 / 16;
                    }
                    $lum[$y + 1][$x] += $error * 5 / 16;
                    if ($x + 1 < $width) {
                        $lum[$y + 1][$x + 1] += $error * 1 / 16;
                    }
                }
            }
        }

        return $mono;
    }

    /**
     * Empaqueta el mapa monocromo en el comando `GS v 0` (modo normal):
     * `1D 76 30 m xL xH yL yH [datos]`, con los datos fila por fila y cada byte
     * representando 8 puntos horizontales (bit más significativo = izquierda).
     *
     * @param  array<int, array<int, bool>>  $mono
     */
    private function rasterCommand(array $mono, int $width, int $height): string
    {
        $bytesPerRow = intdiv($width, 8);
        $data = '';

        for ($y = 0; $y < $height; $y++) {
            for ($byteX = 0; $byteX < $bytesPerRow; $byteX++) {
                $byte = 0;

                for ($bit = 0; $bit < 8; $bit++) {
                    if (! empty($mono[$y][$byteX * 8 + $bit])) {
                        $byte |= 0x80 >> $bit;
                    }
                }

                $data .= chr($byte);
            }
        }

        return chr(0x1D).'v0'.chr(0)
            .chr($bytesPerRow & 0xFF).chr(($bytesPerRow >> 8) & 0xFF)
            .chr($height & 0xFF).chr(($height >> 8) & 0xFF)
            .$data;
    }

    /**
     * @param  array<int, array<int, bool>>  $mono
     */
    private function previewPng(array $mono, int $width, int $height): string
    {
        $img = imagecreate($width, $height);
        imagecolorallocate($img, 255, 255, 255); // fondo (primer color asignado)
        $black = imagecolorallocate($img, 0, 0, 0);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if (! empty($mono[$y][$x])) {
                    imagesetpixel($img, $x, $y, $black);
                }
            }
        }

        ob_start();
        imagepng($img);
        imagedestroy($img);

        return (string) ob_get_clean();
    }
}
