<?php

if (!defined('ABSPATH')) {
    exit;
}

final class ADPW_Excel_Product_Update_Service {
    public static function process_product_update($product_id, $entry, $settings, $mode, &$results) {
        $actualizar_si = !empty($settings['actualizar_si']);
        $actualizar_cat = !empty($settings['actualizar_cat']);

        $product = wc_get_product($product_id);
        if (!$product) {
            $results['errores']++;
            self::append_limited($results['detalles'], 'No se pudo cargar el producto con ID ' . $product_id . '.');
            return;
        }

        $dim_res = [
            'modificado' => false,
            'actualizacion_total' => false,
            'log_producto' => '',
        ];
        $clase_modificada = false;

        if (!empty($mode['actualizar_tam_dimensiones']) && ((float) $entry['peso'] > 0 || (float) $entry['largo'] > 0 || (float) $entry['ancho'] > 0 || (float) $entry['profundidad'] > 0)) {
            $dim_res = self::update_dimensions($product, [
                'peso' => (float) $entry['peso'],
                'largo' => (float) $entry['largo'],
                'ancho' => (float) $entry['ancho'],
                'profundidad' => (float) $entry['profundidad'],
            ], $actualizar_si, (string) ($entry['categoria'] ?? ''), $product_id);
        }

        $tamano = (string) ($entry['tamano'] ?? '');
        if (($actualizar_cat || !empty($mode['solo_tamano_desde_excel'])) && $tamano !== '') {
            $class_res = self::update_shipping_class($product, $tamano);
            if (!empty($class_res['detalles_errores'])) {
                self::append_limited($results['detalles'], $class_res['detalles_errores']);
            }
            if (!empty($class_res['log_producto'])) {
                self::append_limited($results['detalles'], $class_res['log_producto']);
            }
            $clase_modificada = !empty($class_res['modificado']);
        }

        if (!empty($dim_res['modificado'])) {
            if (!empty($dim_res['actualizacion_total'])) {
                $results['totales']++;
            } else {
                $results['parciales']++;
            }
            if (!empty($dim_res['log_producto'])) {
                self::append_limited($results['productos_modificados'], $dim_res['log_producto']);
            }
        }

        if (!empty($dim_res['modificado']) || $clase_modificada) {
            $results['totales']++;
        }
    }

    public static function update_dimensions($product, $incoming, $actualizar_si, $categoria, $product_id) {
        $modificado = false;
        $actualizacion_total = false;
        $log_producto = '';

        $peso_actual = $product->get_weight();
        $largo_actual = $product->get_length();
        $ancho_actual = $product->get_width();
        $profundidad_actual = $product->get_height();

        if ($actualizar_si || !$peso_actual || !$largo_actual || !$ancho_actual || !$profundidad_actual) {
            $log_producto .= "Actualizando el producto {$product->get_name()} ({$product_id}) de la categoría {$categoria}; ";

            if (($actualizar_si || !$peso_actual) && $incoming['peso'] > 0) {
                $product->set_weight($incoming['peso']);
                $log_producto .= "Peso {$peso_actual} -> {$incoming['peso']}; ";
                $modificado = true;
            }
            if (($actualizar_si || !$largo_actual) && $incoming['largo'] > 0) {
                $product->set_length($incoming['largo']);
                $log_producto .= "Largo {$largo_actual} -> {$incoming['largo']}; ";
                $modificado = true;
            }
            if (($actualizar_si || !$ancho_actual) && $incoming['ancho'] > 0) {
                $product->set_width($incoming['ancho']);
                $log_producto .= "Ancho {$ancho_actual} -> {$incoming['ancho']}; ";
                $modificado = true;
            }
            if (($actualizar_si || !$profundidad_actual) && $incoming['profundidad'] > 0) {
                $product->set_height($incoming['profundidad']);
                $log_producto .= "Profundidad {$profundidad_actual} -> {$incoming['profundidad']}; ";
                $modificado = true;
            }

            if ($actualizar_si || (!$peso_actual && !$largo_actual && !$ancho_actual && !$profundidad_actual)) {
                $actualizacion_total = true;
            }

            if ($modificado) {
                $product->save();
            }
        }

        return [
            'modificado' => $modificado,
            'actualizacion_total' => $actualizacion_total,
            'log_producto' => $log_producto,
        ];
    }

    public static function update_shipping_class($product, $tamano) {
        $shipping_class = get_term_by('name', $tamano, 'product_shipping_class') ?: get_term_by('slug', $tamano, 'product_shipping_class');
        if (!$shipping_class) {
            return [
                'modificado' => false,
                'detalles_errores' => "Clase de envío '{$tamano}' no encontrada.",
                'log_producto' => '',
            ];
        }

        if ((int) $product->get_shipping_class_id() === (int) $shipping_class->term_id) {
            return [
                'modificado' => false,
                'detalles_errores' => '',
                'log_producto' => '',
            ];
        }

        $clase_actual = $product->get_shipping_class();
        $product->set_shipping_class_id($shipping_class->term_id);
        $product->save();

        return [
            'modificado' => true,
            'detalles_errores' => '',
            'log_producto' => 'Clase de envío modificada para ' . $product->get_name() . ' (ID: ' . $product->get_id() . ') - Clase original: ' . $clase_actual . '; Nueva clase: ' . $shipping_class->name . '.',
        ];
    }

    private static function append_limited(&$target, $message, $limit = 250) {
        if (!is_array($target)) {
            $target = [];
        }
        if (count($target) >= $limit) {
            return;
        }
        $target[] = $message;
    }
}
