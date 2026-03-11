<?php

if (!defined('ABSPATH')) {
    exit;
}

final class ADPW_Excel_Import_Page {
    public static function render_page() {
        echo '<div class="wrap">';
        echo '<h1>Importar Excel</h1>';

        $settings = ADPW_Settings::get();

        echo '<p><strong>Configuración activa:</strong> ';
        echo 'Actualizar siempre: ' . (!empty($settings['actualizar_si']) ? 'Sí' : 'No') . ' | ';
        echo 'Actualizar dimensiones: ' . (!empty($settings['actualizar_tam']) ? 'Sí' : 'No') . ' | ';
        echo 'Actualizar tamaño: ' . (!empty($settings['actualizar_cat']) ? 'Sí' : 'No');
        echo '. <a href="' . esc_url(admin_url('admin.php?page=adpw-settings')) . '">Cambiar configuración</a></p>';

        if (isset($_POST['adpw_importar_excel'])) {
            $resultados = ADPW_Excel_Import_Service::process_upload($_FILES['archivo_excel'] ?? [], $settings);
            self::render_import_result($resultados);
        }

        echo '<form method="post" enctype="multipart/form-data">';
        echo '<input type="file" name="archivo_excel" required> <br /><br />';
        echo '<button type="submit" name="adpw_importar_excel" class="button button-primary">Importar</button>';
        echo '</form>';

        echo '</div>';
    }

    private static function render_import_result($resultados) {
        echo '<div id="resultado_importacion" style="margin-top:16px;">';

        if (!empty($resultados['error_general'])) {
            echo '<div class="notice notice-error"><p>' . esc_html($resultados['error_general']) . '</p></div>';
            if (!empty($resultados['detalles'])) {
                echo '<ul>';
                foreach ($resultados['detalles'] as $detalle) {
                    echo '<li>' . esc_html($detalle) . '</li>';
                }
                echo '</ul>';
            }
            echo '</div>';
            return;
        }

        echo '<p><strong>Resultados de la importación:</strong></p>';
        echo '<ul>';
        echo '<li>Actualizaciones Totales: ' . esc_html((string) ($resultados['totales'] ?? 0)) . '</li>';
        echo '<li>Actualizaciones Parciales: ' . esc_html((string) ($resultados['parciales'] ?? 0)) . '</li>';
        echo '<li>Errores: ' . esc_html((string) ($resultados['errores'] ?? 0)) . '</li>';

        if (!empty($resultados['detalles'])) {
            echo '<li><strong>Detalles:</strong><ul>';
            foreach ($resultados['detalles'] as $detalle) {
                echo '<li>' . esc_html($detalle) . '</li>';
            }
            echo '</ul></li>';
        }

        if (!empty($resultados['productos_modificados'])) {
            echo '<li><strong>Productos modificados:</strong><ul>';
            foreach ($resultados['productos_modificados'] as $detalle) {
                echo '<li>' . esc_html($detalle) . '</li>';
            }
            echo '</ul></li>';
        }

        echo '</ul>';
        echo '</div>';
    }
}
