<?php

if (!defined('ABSPATH')) {
    exit;
}

final class ADPW_Category_Metadata_Save_Service {
    public static function save_from_request($metadata_by_category, $settings) {
        if (!is_array($metadata_by_category) || empty($metadata_by_category)) {
            return ['mensaje' => 'No hubo cambios para guardar.'];
        }

        $valid_shipping_slugs = ADPW_Category_Metadata_Manager::get_valid_shipping_slugs();
        if ($valid_shipping_slugs === null) {
            return ['error' => 'No se pudieron validar las clases de envío disponibles.'];
        }

        $saved_count = 0;
        $category_ids = [];

        foreach ($metadata_by_category as $category_id => $metadata) {
            $term_id = absint($category_id);
            if (!$term_id) {
                continue;
            }

            ADPW_Category_Metadata_Manager::save_category_metadata($term_id, (array) $metadata, $valid_shipping_slugs);
            $saved_count++;
            $category_ids[] = $term_id;
        }

        $result = [
            'mensaje' => sprintf('Se actualizaron %d categorías.', $saved_count),
        ];

        if (empty($settings['actualizar_productos_desde_categorias']) || empty($category_ids)) {
            return $result;
        }

        $job = ADPW_Category_Update_Queue_Manager::start_job($category_ids, (int) ($settings['categorias_por_lote'] ?? 20));
        if (!empty($job['error_general'])) {
            $result['detalle_productos'] = $job['error_general'];
        } elseif ((int) ($job['total_entries'] ?? 0) === 0) {
            $result['detalle_productos'] = 'No hay productos afectados para actualizar.';
        } else {
            $result['detalle_productos'] = sprintf(
                'Actualización de productos iniciada en segundo plano. Job ID: %s. Productos en cola: %d.',
                $job['job_id'] ?? 'N/A',
                (int) ($job['total_entries'] ?? 0)
            );
        }

        return $result;
    }
}
