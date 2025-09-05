<?php
/*
Plugin Name: Alpina Checklist Plugin
Description: Plugin para criar e gerenciar checklists de desenvolvimento de sites. Nessa versão ele salva os checkboxes marcados em um arquivo JSON.
Version: 3.0.1
Author: Alpina Digital
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}


class Alpina_Checklist_Plugin
{
    private $checklist_data;
    private $checked_items;
    private $checked_file;

    public function __construct()
    {
        $this->checked_file = plugin_dir_path(__FILE__) . 'checked-items.json';
        add_action('init', array($this, 'load_checklist_data'));
        add_action('admin_menu', array($this, 'add_checklist_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_alpina_checklist_toggle', array($this, 'ajax_toggle_checkbox'));
    }

    /**
     * Inicia os scripts e estilos necessários para a página de administração, se estiver na página correta.
     */
    public function enqueue_admin_scripts()
    {
        if (is_admin() && isset($_GET['page']) && $_GET['page'] === 'revisar_checklists') {
            wp_enqueue_script('jquery-ui-tabs');
            wp_enqueue_style('jquery-ui', '//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
        }
    }

    /**
     * Inicializa os dados do checklist a partir do arquivo JSON.
     */
    public function load_checklist_data()
    {
        $json_file = plugin_dir_path(__FILE__) . 'checklist-data.json';
        if (file_exists($json_file)) {
            $json_content = file_get_contents($json_file);
            $this->checklist_data = json_decode($json_content, true);
        } else {
            $this->checklist_data = array();
        }
        // Carrega os itens marcados do arquivo físico
        if (file_exists($this->checked_file)) {
            $checked_content = file_get_contents($this->checked_file);
            $this->checked_items = json_decode($checked_content, true);
        } else {
            $this->checked_items = array();
        }
    }

    /**
     * AJAX handler para marcar/desmarcar checkbox em tempo real
     */
    public function ajax_toggle_checkbox()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Sem permissão.');
        }
        $key = isset($_POST['key']) ? sanitize_title($_POST['key']) : '';
        $checked = isset($_POST['checked']) && $_POST['checked'] == 'true';
        if (!$key) {
            wp_send_json_error('Chave inválida.');
        }
        // Carrega o estado atual
        $items = array();
        if (file_exists($this->checked_file)) {
            $items = json_decode(file_get_contents($this->checked_file), true);
            if (!is_array($items)) $items = array();
        }
        $items[$key] = $checked;
        file_put_contents($this->checked_file, json_encode($items));
        wp_send_json_success();
    }

    /**
     * Cria o menu de administração para o plugin.
     */
    public function add_checklist_menu()
    {
        add_menu_page(
            'Revisar Checklists',
            'Checklists',
            'manage_options',
            'revisar_checklists',
            array($this, 'checklist_review_page'),
            'dashicons-yes',
            99
        );
    }

    /**
     * Renderiza a página de revisão dos checklists.
     */
    public function checklist_review_page()
    {

        // Protege o acesso: apenas o usuário 'alpina' pode acessar
        $current_user = wp_get_current_user();
        // if ($current_user->user_login !== 'alpina') {
        //     echo '<div class="wrap"><h1>Revisar Checklists</h1><p style="color:red;">Acesso restrito.</p></div>';
        //     return;
        // }

        if (empty($this->checklist_data)) {
            echo '<div class="wrap"><h1>Revisar Checklists</h1><p>Nenhum dado encontrado no JSON.</p></div>';
            return;
        }

        $active_tab = isset($_GET['tab']) ? intval($_GET['tab']) : 0;

        echo '<div class="wrap"><h1>Revisar Checklists</h1>';
        echo '<input type="hidden" name="active_tab" id="active_tab" value="' . esc_attr($active_tab) . '">';
        echo '<div id="tabs">';
        echo '<ul>';

        foreach ($this->checklist_data as $index => $category) {
            echo '<li><a href="#tab-' . $index . '">' . esc_html($category['category']) . '</a></li>';
        }

        echo '</ul>';

        foreach ($this->checklist_data as $index => $category) {
            echo '<div id="tab-' . $index . '">';
            if (!empty($category['items'])) {
                $i = 1;
                foreach ($category['items'] as $item) {
                    $key = sanitize_title($item['title']);
                    $checked = (isset($this->checked_items[$key]) && $this->checked_items[$key]) ? 'checked' : '';
                    $na_key = $key . '_na';
                    $na_checked = (isset($this->checked_items[$na_key]) && $this->checked_items[$na_key]) ? 'checked' : '';
                    echo '<p><strong>' . $i++ . ') ' . esc_html($item['title']) . '</strong>';
                    echo '<br><br><label><input type="checkbox" class="alpina-checklist-checkbox" data-key="' . esc_attr($key) . '" ' . $checked . '> Checado</label>';
                    echo '<br><label style="color:#666;"><input type="checkbox" class="alpina-checklist-checkbox-na" data-key="' . esc_attr($na_key) . '" ' . $na_checked . '> Não aplicável a esse projeto</label>';
                    echo !empty($item['description']) ? '<br><br>Obs: ' . esc_attr($item['description']) : '';
                    echo '<hr>';
                    echo '</p>';
                }
            } else {
                echo '<p>Nenhum item encontrado nesta categoria.</p>';
            }
            echo '</div>';
        }

        echo '</div>';
        echo '</div>';

        // Script para tabs e AJAX
        echo '<script>
            jQuery(document).ready(function($) {
                var activeTab = ' . $active_tab . ';
                $("#tabs").tabs({
                    active: activeTab,
                    activate: function(event, ui) {
                        $("#active_tab").val(ui.newTab.index());
                    }
                });
                $(".alpina-checklist-checkbox, .alpina-checklist-checkbox-na").on("change", function() {
                    var key = $(this).data("key");
                    var checked = $(this).is(":checked");
                    $.post(ajaxurl, {
                        action: "alpina_checklist_toggle",
                        key: key,
                        checked: checked
                    });
                });
            });
        </script>';
    }
}

new Alpina_Checklist_Plugin();
