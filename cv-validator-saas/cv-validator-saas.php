<?php
/**
 * Plugin Name: CV Validator SaaS
 * Description: Sube y valida CVs en PDF con PDFParser, guarda texto en .txt, usa DeepSeek API vía OpenAI-compatible Chat, valida con taxonomías dinámicas de industria, especializaciones, posiciones preferidas y valores personales, y gestiona CVs como CPT con campos, columnas y asignación de términos.
 * Version:     2.3.2
 * Author:      Konstantin WDK
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CV_Validator_SaaS_Plugin {
    // Option keys
    private $option_api_key                  = 'cv_validator_saas_deepseek_api_key';
    private $option_endpoint                 = 'cv_validator_saas_deepseek_endpoint';
    private $option_model                    = 'cv_validator_saas_deepseek_model';
    private $option_instructions             = 'cv_validator_saas_deepseek_instructions';
    private $option_selected_industries      = 'cv_validator_saas_selected_industries';
    private $option_selected_specializations = 'cv_validator_saas_selected_specializations';
    private $option_selected_positions       = 'cv_validator_saas_selected_positions';
    private $option_selected_values          = 'cv_validator_saas_selected_values';

    // Meta keys
    private $meta_pdf      = 'cv_pdf_url';
    private $meta_txt      = 'cv_txt_url';
    private $meta_decision = 'cv_decision';
    private $meta_reason   = 'cv_reason';

    public function __construct() {
        add_action( 'init', array( $this, 'register_cv_post_type' ) );
        add_action( 'init', array( $this, 'register_taxonomies' ) );
        add_action( 'init', array( $this, 'register_shortcodes' ) );
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'settings_init' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_cv_meta_boxes' ) );
        add_filter( 'manage_cv_posts_columns', array( $this, 'cv_columns' ) );
        add_action( 'manage_cv_posts_custom_column', array( $this, 'cv_custom_column' ), 10, 2 );
    }

    public static function activate() {
        $plugin = new self();
        $plugin->register_cv_post_type();
        $plugin->register_taxonomies();
        flush_rewrite_rules();
    }

    public function enqueue_admin_assets( $hook ) {
        if ( $hook !== 'toplevel_page_cv_validator_saas' ) {
            return;
        }
        // CSS for bubbles
        wp_add_inline_style( 'wp-admin', "
            .cv-bubble-container { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:8px; }
            .cv-bubble { padding:6px 12px; border:1px solid #ccc; border-radius:999px; cursor:pointer; user-select:none; }
            .cv-bubble.selected { background:#0073aa; color:#fff; border-color:#0073aa; }
            .cv-bubble-container input[type=hidden] { display:none; }
        " );
        // JS for toggling bubbles (fixed quotation)
        wp_add_inline_script( 'jquery-core', "
            jQuery(function($){
              $('.cv-bubble-container').on('click', '.cv-bubble', function(){
                var \$b = $(this),
                    slug = \$b.data('slug'),
                    container = \$b.closest('.cv-bubble-container'),
                    field = container.data('field');
                \$b.toggleClass('selected');
                if (\$b.hasClass('selected')) {
                  $('<input>').attr({
                    type: 'hidden',
                    name: field + '[]',
                    value: slug
                  }).appendTo(container);
                } else {
                  container.find('input[name=\"' + field + '[]\"]' +
                                 '[value=\"' + slug + '\"]').remove();
                }
              });
            });
        " );
    }

    public function register_cv_post_type() {
        $labels = array(
            'name'               => __( 'CVs', 'cv-validator-saas' ),
            'singular_name'      => __( 'CV', 'cv-validator-saas' ),
            'menu_name'          => __( 'CVs', 'cv-validator-saas' ),
            'add_new'            => __( 'Añadir Nuevo', 'cv-validator-saas' ),
            'add_new_item'       => __( 'Añadir Nuevo CV', 'cv-validator-saas' ),
            'edit_item'          => __( 'Editar CV', 'cv-validator-saas' ),
            'view_item'          => __( 'Ver CV', 'cv-validator-saas' ),
            'all_items'          => __( 'Todos los CVs', 'cv-validator-saas' ),
            'search_items'       => __( 'Buscar CVs', 'cv-validator-saas' ),
            'not_found'          => __( 'No se encontraron CVs', 'cv-validator-saas' ),
            'not_found_in_trash' => __( 'No se encontraron CVs en la papelera', 'cv-validator-saas' ),
        );
        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'capability_type'    => 'post',
            'supports'           => array( 'title' ),
            'menu_icon'          => 'dashicons-media-document',
        );
        register_post_type( 'cv', $args );
    }

    public function register_taxonomies() {
        register_taxonomy( 'cv_industry', 'cv', array(
            'labels'       => array(
                'name'          => __( 'Industrias', 'cv-validator-saas' ),
                'singular_name' => __( 'Industria', 'cv-validator-saas' ),
            ),
            'public'       => true,
            'show_ui'      => true,
            'show_in_rest' => true,
            'hierarchical' => false,
            'rewrite'      => array( 'slug' => 'cv-industry' ),
        ) );
        register_taxonomy( 'cv_specialization', 'cv', array(
            'labels'       => array(
                'name'          => __( 'Especializaciones', 'cv-validator-saas' ),
                'singular_name' => __( 'Especialización', 'cv-validator-saas' ),
            ),
            'public'       => true,
            'show_ui'      => true,
            'show_in_rest' => true,
            'hierarchical' => false,
            'rewrite'      => array( 'slug' => 'cv-specialization' ),
        ) );
        register_taxonomy( 'cv_position', 'cv', array(
            'labels'       => array(
                'name'          => __( 'Posiciones preferidas', 'cv-validator-saas' ),
                'singular_name' => __( 'Posición preferida', 'cv-validator-saas' ),
            ),
            'public'       => true,
            'show_ui'      => true,
            'show_in_rest' => true,
            'hierarchical' => false,
            'rewrite'      => array( 'slug' => 'cv-position' ),
        ) );
        register_taxonomy( 'cv_personal_value', 'cv', array(
            'labels'       => array(
                'name'          => __( 'Valores personales', 'cv-validator-saas' ),
                'singular_name' => __( 'Valor personal', 'cv-validator-saas' ),
            ),
            'public'       => true,
            'show_ui'      => true,
            'show_in_rest' => true,
            'hierarchical' => false,
            'rewrite'      => array( 'slug' => 'cv-personal-value' ),
        ) );
    }

    public function register_shortcodes() {
        add_shortcode( 'cv_validator_form', array( $this, 'frontend_form' ) );
        add_shortcode( 'cv_validator_list', array( $this, 'frontend_list' ) );
    }

    public function add_admin_menu() {
        add_menu_page(
            __( 'CV Validator', 'cv-validator-saas' ),
            __( 'CV Validator', 'cv-validator-saas' ),
            'manage_options',
            'cv_validator_saas',
            array( $this, 'options_page' ),
            'dashicons-media-document',
            60
        );
    }

    public function settings_init() {
        register_setting( 'cv_validator_saas_settings', $this->option_api_key );
        register_setting( 'cv_validator_saas_settings', $this->option_endpoint );
        register_setting( 'cv_validator_saas_settings', $this->option_model );
        register_setting( 'cv_validator_saas_settings', $this->option_instructions );
        register_setting( 'cv_validator_saas_settings', $this->option_selected_industries );
        register_setting( 'cv_validator_saas_settings', $this->option_selected_specializations );
        register_setting( 'cv_validator_saas_settings', $this->option_selected_positions );
        register_setting( 'cv_validator_saas_settings', $this->option_selected_values );

        add_settings_section(
            'cv_validator_saas_section',
            __( 'DeepSeek API y Taxonomías Activas', 'cv-validator-saas' ),
            null,
            'cv_validator_saas'
        );

        add_settings_field(
            $this->option_api_key,
            __( 'DeepSeek API Key', 'cv-validator-saas' ),
            array( $this, 'api_key_render' ),
            'cv_validator_saas',
            'cv_validator_saas_section'
        );
        add_settings_field(
            $this->option_endpoint,
            __( 'API Endpoint', 'cv-validator-saas' ),
            array( $this, 'endpoint_render' ),
            'cv_validator_saas',
            'cv_validator_saas_section'
        );
        add_settings_field(
            $this->option_model,
            __( 'Model', 'cv-validator-saas' ),
            array( $this, 'model_render' ),
            'cv_validator_saas',
            'cv_validator_saas_section'
        );
        add_settings_field(
            $this->option_instructions,
            __( 'System Instructions', 'cv-validator-saas' ),
            array( $this, 'instructions_render' ),
            'cv_validator_saas',
            'cv_validator_saas_section'
        );
        add_settings_field(
            $this->option_selected_industries,
            __( 'Industrias activas', 'cv-validator-saas' ),
            array( $this, 'render_industries_selector' ),
            'cv_validator_saas',
            'cv_validator_saas_section'
        );
        add_settings_field(
            $this->option_selected_specializations,
            __( 'Especializaciones activas', 'cv-validator-saas' ),
            array( $this, 'render_specializations_selector' ),
            'cv_validator_saas',
            'cv_validator_saas_section'
        );
        add_settings_field(
            $this->option_selected_positions,
            __( 'Posiciones activas', 'cv-validator-saas' ),
            array( $this, 'render_positions_selector' ),
            'cv_validator_saas',
            'cv_validator_saas_section'
        );
        add_settings_field(
            $this->option_selected_values,
            __( 'Valores personales activos', 'cv-validator-saas' ),
            array( $this, 'render_values_selector' ),
            'cv_validator_saas',
            'cv_validator_saas_section'
        );
    }

    public function api_key_render() {
        $v = get_option( $this->option_api_key, '' );
        echo '<input type="text" name="'.esc_attr( $this->option_api_key ).'" value="'.esc_attr( $v ).'" style="width:400px;">';
    }

    public function endpoint_render() {
        $v = get_option( $this->option_endpoint, 'https://api.deepseek.com/v1/chat/completions' );
        echo '<input type="text" name="'.esc_attr( $this->option_endpoint ).'" value="'.esc_attr( $v ).'" style="width:400px;">';
    }

    public function model_render() {
        $v = get_option( $this->option_model, 'deepseek-chat' );
        echo '<input type="text" name="'.esc_attr( $this->option_model ).'" value="'.esc_attr( $v ).'" style="width:200px;">';
    }

    public function instructions_render() {
        $v = get_option( $this->option_instructions, '' );
        if ( empty( trim( $v ) ) ) {
            $v = <<<EOT
Eres un asistente experto en reclutamiento para la industria de {industria}.
Tu misión es analizar un currículum en texto plano y determinar si el candidato es **“apto”** o **“no apto”**, basándote en las siguientes taxonomías configuradas en el plugin:

  • Especializaciones buscadas: {{Especializaciones}}
  • Posiciones preferidas:     {{Posiciones}}
  • Valores personales:        {{Valores}}

--------------------------------------------
1. ANALÍTICA DE COINCIDENCIAS
   - Para **Especializaciones** y **Posiciones**:
     1. Busca menciones textuales o sinónimos de cada término.
     2. Cuenta cuántas aparecen.
     3. Calcula porcentaje de cobertura:
        `porcentaje = (términos encontrados / términos esperados) × 100`.
   - Para **Valores personales**:
     1. Identifica evidencias de comportamiento o logros que indiquen cada valor (por ejemplo, “lideré un equipo” → Liderazgo).
     2. Cuenta cuántos valores quedan demostrados.

2. CRITERIO DE APTITUD
   - **“Apto”** si:
     • Al menos el 60 % de las especializaciones • Y al menos el 50 % de las posiciones • Y al menos 3 valores quedan demostrados.
   - **“No apto”** en cualquier otro caso.

3. FORMATO DE SALIDA
   - **Primera línea**: solo “apto” o “no apto”.
   - **Segunda línea**: resumen numérico de coincidencias. Ejemplo:
     ```
     Especializaciones: 3/5 (60 %); Posiciones: 2/4 (50 %); Valores: 4/6
     ```
   - **A partir de la tercera línea**: lista de evidencias concretas, separadas por viñetas:
     - Para cada especialización o posición encontrada, indica la frase exacta o palabra clave.
     - Para cada valor demostrado, referencia la sección del CV donde se infiere.
     - Si es “no apto”, añade además qué falta o qué queda muy por debajo del umbral.

4. TIPS ADICIONALES
   - Normaliza el texto (minúsculas, eliminar acentos, sinónimos básicos).
   - Ignora experiencias muy antiguas (antes de hace 10 años) a menos que sean proyectos destacables.
   - Valora la calidad de la experiencia (años, rol, tamaño de la empresa) cuando haya empates.
EOT;
        }
        echo '<textarea name="'.esc_attr( $this->option_instructions ).'" rows="15" cols="80" style="font-family:monospace;">'.esc_textarea( $v ).'</textarea>';
        echo '<p class="description"><strong>Variables disponibles:</strong><br>
            <code>{industria}</code>: nombre de la industria seleccionada<br>
            <code>{{Especializaciones}}</code>: lista de especializaciones activas<br>
            <code>{{Posiciones}}</code>: lista de posiciones activas<br>
            <code>{{Valores}}</code>: lista de valores personales activos</p>';
    }

    private function render_taxonomy_bubbles( $option_key, $taxonomy ) {
        $terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
        $saved = (array) get_option( $option_key, array() );
        echo '<div class="cv-bubble-container" data-field="'.esc_attr( $option_key ).'">';
        foreach ( $terms as $t ) {
            $sel = in_array( $t->slug, $saved ) ? ' selected' : '';
            if ( $sel ) {
                echo '<input type="hidden" name="'.esc_attr( $option_key ).'[]" value="'.esc_attr( $t->slug ).'">';
            }
            printf(
                '<div class="cv-bubble%1$s" data-slug="%2$s">%3$s</div>',
                $sel,
                esc_attr( $t->slug ),
                esc_html( $t->name )
            );
        }
        echo '</div>';
    }

    public function render_industries_selector() {
        $this->render_taxonomy_bubbles( $this->option_selected_industries, 'cv_industry' );
    }
    public function render_specializations_selector() {
        $this->render_taxonomy_bubbles( $this->option_selected_specializations, 'cv_specialization' );
    }
    public function render_positions_selector() {
        $this->render_taxonomy_bubbles( $this->option_selected_positions, 'cv_position' );
    }
    public function render_values_selector() {
        $this->render_taxonomy_bubbles( $this->option_selected_values, 'cv_personal_value' );
    }

    public function options_page() { ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'CV Validator SaaS', 'cv-validator-saas' ); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'cv_validator_saas_settings' ); ?>
                <?php do_settings_sections( 'cv_validator_saas' ); ?>
                <?php submit_button(); ?>
            </form>

            <hr>

            <h2><?php esc_html_e( 'Importar valores de demostración', 'cv-validator-saas' ); ?></h2>
            <form method="post">
                <?php wp_nonce_field( 'cv_validator_saas_import_demo', 'cv_validator_saas_import_demo_nonce' ); ?>
                <?php submit_button( __( 'Importar Demo', 'cv-validator-saas' ), 'secondary', 'import_demo' ); ?>
            </form>
            <?php
            if ( ! empty( $_POST['import_demo'] ) ) {
                if ( ! isset( $_POST['cv_validator_saas_import_demo_nonce'] ) || ! wp_verify_nonce( $_POST['cv_validator_saas_import_demo_nonce'], 'cv_validator_saas_import_demo' ) ) {
                    echo '<div class="notice notice-error"><p>'. esc_html__( 'Error de seguridad al importar demo.', 'cv-validator-saas' ) .'</p></div>';
                } else {
                    $this->import_demo_terms();
                    echo '<div class="notice notice-success"><p>'. esc_html__( 'Valores de demostración importados correctamente.', 'cv-validator-saas' ) .'</p></div>';
                }
            }
            ?>

            <hr>

            <h2><?php esc_html_e( 'Analizar CV (Back-end)', 'cv-validator-saas' ); ?></h2>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field( 'cv_validator_saas_admin', 'cv_validator_saas_nonce' ); ?>
                <input type="file" name="cv_file" accept="application/pdf" required>
                <?php submit_button( __( 'Analizar CV', 'cv-validator-saas' ), 'primary', 'analyze_cv' ); ?>
            </form>
            <?php
            if ( ! empty( $_POST['analyze_cv'] ) ) {
                if ( ! isset( $_POST['cv_validator_saas_nonce'] ) || ! wp_verify_nonce( $_POST['cv_validator_saas_nonce'], 'cv_validator_saas_admin' ) ) {
                    echo '<div class="notice notice-error"><p>'.esc_html__( 'Error de seguridad', 'cv-validator-saas' ).'</p></div>';
                } else {
                    $this->process_file( $_FILES['cv_file'] );
                }
            }
            ?>
        </div>
    <?php }

    private function import_demo_terms() {
        $demo = array(
            'cv_industry' => array(
                'Moda','Tecnología','Hostelería','Salud','Finanzas',
                'Educación','Construcción','Automotriz','Telecomunicaciones','Energía',
                'Logística','Retail','Marketing','Legal','Recursos Humanos'
            ),
            'cv_specialization' => array(
                'Gestión de proyectos','Desarrollo de producto','Marketing digital','Análisis de datos','Atención al cliente',
                'Diseño gráfico','UX/UI','Desarrollo de software','Ciberseguridad','Cadena de suministro',
                'Ventas corporativas','Relaciones públicas','Finanzas corporativas','Control de calidad','I+D'
            ),
            'cv_position' => array(
                'Project Manager','Product Owner','Digital Marketing Specialist','Data Analyst','Customer Success Manager',
                'UX Designer','Software Engineer','Cybersecurity Specialist','Supply Chain Coordinator','Sales Representative',
                'HR Generalist','Financial Controller','Quality Assurance Engineer','R&D Scientist','PR Manager'
            ),
            'cv_personal_value' => array(
                'Liderazgo','Innovación','Trabajo en equipo','Adaptabilidad','Comunicación efectiva',
                'Responsabilidad','Empatía','Proactividad','Orientación a resultados','Pensamiento estratégico',
                'Creatividad','Integridad','Resiliencia','Atención al detalle','Organización'
            ),
        );
        foreach ( $demo as $tax => $terms ) {
            foreach ( $terms as $term ) {
                if ( ! term_exists( $term, $tax ) ) {
                    wp_insert_term( $term, $tax );
                }
            }
        }
    }

    public function add_cv_meta_boxes() {
        add_meta_box( 'cv_details', __( 'Detalles del CV', 'cv-validator-saas' ), array( $this, 'render_cv_meta_box' ), 'cv', 'normal', 'default' );
    }

    public function render_cv_meta_box( $post ) {
        $pdf    = get_post_meta( $post->ID, $this->meta_pdf, true );
        $txt    = get_post_meta( $post->ID, $this->meta_txt, true );
        $dec    = get_post_meta( $post->ID, $this->meta_decision, true );
        $reason = get_post_meta( $post->ID, $this->meta_reason, true );
        echo '<p><strong>'.esc_html__( 'Decisión:', 'cv-validator-saas' ).'</strong> '.esc_html( ucfirst( $dec ) ).'</p>';
        echo '<p><strong>'.esc_html__( 'Razón:', 'cv-validator-saas' ).'</strong> '.nl2br( esc_html( $reason ) ).'</p>';
        echo '<p><strong>'.esc_html__( 'PDF:', 'cv-validator-saas' ).'</strong> '.( $pdf ? '<a href="'.esc_url( $pdf ).'" target="_blank">'.esc_html__( 'Ver PDF', 'cv-validator-saas' ).'</a>' : esc_html__( 'No disponible', 'cv-validator-saas' ) ).'</p>';
        echo '<p><strong>'.esc_html__( 'TXT:', 'cv-validator-saas' ).'</strong> '.( $txt ? '<a href="'.esc_url( $txt ).'" target="_blank">'.esc_html__( 'Ver TXT', 'cv-validator-saas' ).'</a>' : esc_html__( 'No disponible', 'cv-validator-saas' ) ).'</p>';
    }

    public function cv_columns( $columns ) {
        return array(
            'cb'       => $columns['cb'],
            'title'    => __( 'Título', 'cv-validator-saas' ),
            'decision' => __( 'Decisión', 'cv-validator-saas' ),
            'reason'   => __( 'Razón', 'cv-validator-saas' ),
            'pdf'      => __( 'PDF', 'cv-validator-saas' ),
            'txt'      => __( 'TXT', 'cv-validator-saas' ),
            'date'     => $columns['date'],
        );
    }

    public function cv_custom_column( $column, $post_id ) {
        switch ( $column ) {
            case 'decision':
                echo esc_html( get_post_meta( $post_id, $this->meta_decision, true ) );
                break;
            case 'reason':
                echo esc_html( get_post_meta( $post_id, $this->meta_reason, true ) );
                break;
            case 'pdf':
                $url = get_post_meta( $post_id, $this->meta_pdf, true );
                if ( $url ) echo '<a href="'.esc_url( $url ).'" target="_blank">PDF</a>';
                break;
            case 'txt':
                $url = get_post_meta( $post_id, $this->meta_txt, true );
                if ( $url ) echo '<a href="'.esc_url( $url ).'" target="_blank">TXT</a>';
                break;
        }
    }

    public function frontend_form() {
        ob_start(); ?>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field( 'cv_validator_saas_front', 'cv_validator_saas_front_nonce' ); ?>

            <p>
                <label><?php esc_html_e( 'Industria:', 'cv-validator-saas' ); ?></label><br>
                <select name="industry" required>
                    <option value=""><?php esc_html_e( 'Selecciona industria', 'cv-validator-saas' ); ?></option>
                    <?php
                    $selected = (array) get_option( $this->option_selected_industries, array() );
                    $industries = $selected
                        ? get_terms( array( 'taxonomy'=>'cv_industry', 'hide_empty'=>false, 'slug'=>$selected ) )
                        : get_terms( array( 'taxonomy'=>'cv_industry', 'hide_empty'=>false ) );
                    if ( ! is_wp_error( $industries ) ) {
                        foreach ( $industries as $ind ) {
                            echo '<option value="'.esc_attr( $ind->slug ).'">'.esc_html( $ind->name ).'</option>';
                        }
                    }
                    ?>
                </select>
            </p>

            <p>
                <label><?php esc_html_e( 'Especializaciones:', 'cv-validator-saas' ); ?></label><br>
                <?php
                $selected = (array) get_option( $this->option_selected_specializations, array() );
                $specs = $selected
                    ? get_terms( array( 'taxonomy'=>'cv_specialization', 'hide_empty'=>false, 'slug'=>$selected ) )
                    : get_terms( array( 'taxonomy'=>'cv_specialization', 'hide_empty'=>false ) );
                if ( ! is_wp_error( $specs ) ) {
                    foreach ( $specs as $s ) {
                        echo '<label><input type="checkbox" name="specializations[]" value="'.esc_attr( $s->slug ).'"> '.esc_html( $s->name ).'</label><br>';
                    }
                }
                ?>
            </p>

            <p>
                <label><?php esc_html_e( 'Posiciones preferidas:', 'cv-validator-saas' ); ?></label><br>
                <?php
                $selected = (array) get_option( $this->option_selected_positions, array() );
                $positions = $selected
                    ? get_terms( array( 'taxonomy'=>'cv_position', 'hide_empty'=>false, 'slug'=>$selected ) )
                    : get_terms( array( 'taxonomy'=>'cv_position', 'hide_empty'=>false ) );
                if ( ! is_wp_error( $positions ) ) {
                    foreach ( $positions as $p ) {
                        echo '<label><input type="checkbox" name="positions[]" value="'.esc_attr( $p->slug ).'"> '.esc_html( $p->name ).'</label><br>';
                    }
                }
                ?>
            </p>

            <p>
                <label><?php esc_html_e( 'Valores personales:', 'cv-validator-saas' ); ?></label><br>
                <?php
                $selected = (array) get_option( $this->option_selected_values, array() );
                $values = $selected
                    ? get_terms( array( 'taxonomy'=>'cv_personal_value', 'hide_empty'=>false, 'slug'=>$selected ) )
                    : get_terms( array( 'taxonomy'=>'cv_personal_value', 'hide_empty'=>false ) );
                if ( ! is_wp_error( $values ) ) {
                    foreach ( $values as $v ) {
                        echo '<label><input type="checkbox" name="values[]" value="'.esc_attr( $v->slug ).'"> '.esc_html( $v->name ).'</label><br>';
                    }
                }
                ?>
            </p>

            <p>
                <input type="file" name="cv_file" accept="application/pdf" required>
            </p>

            <p>
                <button type="submit" name="submit_cv" class="button button-primary"><?php esc_html_e( 'Enviar CV', 'cv-validator-saas' ); ?></button>
            </p>
        </form>
        <?php
        if ( ! empty( $_POST['submit_cv'] ) ) {
            if ( ! isset( $_POST['cv_validator_saas_front_nonce'] ) || ! wp_verify_nonce( $_POST['cv_validator_saas_front_nonce'], 'cv_validator_saas_front' ) ) {
                echo '<div class="cv-validator-error">'.esc_html__( 'Error de seguridad', 'cv-validator-saas' ).'</div>';
            } elseif ( empty( $_FILES['cv_file'] ) || $_FILES['cv_file']['error'] !== UPLOAD_ERR_OK ) {
                echo '<div class="cv-validator-error">'.esc_html__( 'Error al subir archivo', 'cv-validator-saas' ).'</div>';
            } else {
                $this->process_file( $_FILES['cv_file'] );
            }
        }
        return ob_get_clean();
    }

    public function frontend_list() {
        $cvs = get_posts( array(
            'post_type'      => 'cv',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ) );
        if ( ! $cvs ) {
            return '<p>'.esc_html__( 'No hay CVs disponibles.', 'cv-validator-saas' ).'</p>';
        }
        $out = '<ul class="cv-list">';
        foreach ( $cvs as $cv ) {
            $title = esc_html( get_the_title( $cv ) );
            $link  = get_permalink( $cv );
            $dec   = get_post_meta( $cv->ID, $this->meta_decision, true );
            $out .= '<li><a href="'.esc_url( $link ).'" target="_blank">'.$title.'</a> - '.esc_html( ucfirst( $dec ) ).'</li>';
        }
        $out .= '</ul>';
        return $out;
    }

    private function process_file( $file ) {
        $up = wp_handle_upload( $file, array( 'test_form'=>false ) );
        if ( isset( $up['error'] ) ) {
            echo '<div class="cv-validator-error">'.esc_html( $up['error'] ).'</div>';
            return;
        }
        $pdf_url = $up['url'];

        $uploads = wp_upload_dir();
        $txt_dir = $uploads['basedir'].'/cv-validator-saas-txt';
        if ( ! file_exists( $txt_dir ) ) wp_mkdir_p( $txt_dir );
        $txt_file = $txt_dir.'/'.sanitize_file_name( pathinfo( $file['name'], PATHINFO_FILENAME ) ).'-'.time().'.txt';

        $autoload = plugin_dir_path( __FILE__ ) . 'pdfparser/vendor/autoload.php';
        if ( ! file_exists( $autoload ) ) {
            echo '<div class="cv-validator-error">'.esc_html__( 'Falta vendor de PDFParser', 'cv-validator-saas' ).'</div>';
            return;
        }
        require_once $autoload;
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf    = $parser->parseFile( $up['file'] );
            $text   = $pdf->getText();
        } catch ( Exception $e ) {
            echo '<div class="cv-validator-error">'.esc_html__( 'Error parse PDF:', 'cv-validator-saas' ).' '.esc_html( $e->getMessage() ).'</div>';
            return;
        }
        file_put_contents( $txt_file, $text );
        $txt_url = str_replace( $uploads['basedir'], $uploads['baseurl'], $txt_file );

        $spec_slugs = isset( $_POST['specializations'] )
            ? array_map( 'sanitize_text_field', $_POST['specializations'] )
            : (array) get_option( $this->option_selected_specializations, array() );
        $pos_slugs = isset( $_POST['positions'] )
            ? array_map( 'sanitize_text_field', $_POST['positions'] )
            : (array) get_option( $this->option_selected_positions, array() );
        $val_slugs = isset( $_POST['values'] )
            ? array_map( 'sanitize_text_field', $_POST['values'] )
            : (array) get_option( $this->option_selected_values, array() );
        $industry_slug = isset( $_POST['industry'] )
            ? sanitize_text_field( $_POST['industry'] )
            : ( ( $saved = get_option( $this->option_selected_industries, array() ) ) ? $saved[0] : '' );

        $spec_names = array();
        foreach ( $spec_slugs as $slug ) {
            if ( $t = get_term_by( 'slug', $slug, 'cv_specialization' ) ) {
                $spec_names[] = $t->name;
            }
        }
        $pos_names = array();
        foreach ( $pos_slugs as $slug ) {
            if ( $t = get_term_by( 'slug', $slug, 'cv_position' ) ) {
                $pos_names[] = $t->name;
            }
        }
        $val_names = array();
        foreach ( $val_slugs as $slug ) {
            if ( $t = get_term_by( 'slug', $slug, 'cv_personal_value' ) ) {
                $val_names[] = $t->name;
            }
        }
        $industry_name = '';
        if ( $industry_slug && ( $ind = get_term_by( 'slug', $industry_slug, 'cv_industry' ) ) ) {
            $industry_name = $ind->name;
        }

        $default_keywords = array(
            'diseño','pasarela','estilismo','textil','modelaje','marketing',
            'branding','desfiles','colecciones','portafolio','fashion','moda',
            'patronaje','fotografía','luxury','imagen','styling'
        );
        $keywords = array_unique( array_merge( $default_keywords, array_map( 'strtolower', $spec_names ) ) );
        $found = false;
        foreach ( $keywords as $kw ) {
            if ( stripos( $text, $kw ) !== false ) {
                $found = true;
                break;
            }
        }

        if ( ! $found ) {
            $decision = 'no apto';
            $reason   = 'No hay indicadores de las especializaciones configuradas en el CV.';
        } else {
            $key       = get_option( $this->option_api_key, '' );
            $endpoint  = get_option( $this->option_endpoint, 'https://api.deepseek.com/v1/chat/completions' );
            $model     = get_option( $this->option_model, 'deepseek-chat' );
            $instr_tpl = get_option( $this->option_instructions, '' );
            $instructions = str_replace( '{industria}', $industry_name, $instr_tpl );
            $sys_content   = $instructions
                . "\nEspecializaciones: " . implode( ', ', $spec_names )
                . "\nPosiciones preferidas: " . implode( ', ', $pos_names )
                . "\nValores personales: " . implode( ', ', $val_names );
            $payload = array(
                'model'    => $model,
                'messages' => array(
                    array( 'role' => 'system', 'content' => $sys_content ),
                    array( 'role' => 'user',   'content' => $text ),
                ),
                'stream' => false,
            );
            $resp = wp_remote_post( $endpoint, array(
                'headers' => array(
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $key,
                ),
                'body'    => wp_json_encode( $payload ),
                'timeout' => 60,
            ) );
            if ( is_wp_error( $resp ) ) {
                echo '<div class="cv-validator-error">'.esc_html__( 'Error DeepSeek:', 'cv-validator-saas' ).' '.esc_html( $resp->get_error_message() ).'</div>';
                return;
            }
            $code = wp_remote_retrieve_response_code( $resp );
            $body = wp_remote_retrieve_body( $resp );
            if ( $code !== 200 ) {
                echo '<div class="cv-validator-error">'.esc_html__( 'API error:', 'cv-validator-saas' ).' '.esc_html( $body ).'</div>';
                return;
            }
            $data    = json_decode( $body, true );
            $content = isset( $data['choices'][0]['message']['content'] ) ? $data['choices'][0]['message']['content'] : '';
            $lines   = preg_split( '/\r?\n/', trim( $content ) );
            $first   = strtolower( trim( $lines[0] ) );
            $decision = ( strpos( $first, 'apto' ) !== false ) ? 'apto' : 'no apto';
            $reason   = implode( "\n", array_slice( $lines, 1 ) );
        }

        $title = sprintf( __( 'CV: %s', 'cv-validator-saas' ), pathinfo( $file['name'], PATHINFO_FILENAME ) )
               . ' - ' . date_i18n( 'Y-m-d H:i:s' );
        $pid = wp_insert_post( array(
            'post_title'   => wp_strip_all_tags( $title ),
            'post_content' => '',
            'post_status'  => 'publish',
            'post_type'    => 'cv',
        ) );
        if ( is_wp_error( $pid ) ) {
            echo '<div class="cv-validator-error">'.esc_html__( 'Error crear CV', 'cv-validator-saas' ).' '.esc_html( $pid->get_error_message() ).'</div>';
            return;
        }
        update_post_meta( $pid, $this->meta_pdf,      $pdf_url );
        update_post_meta( $pid, $this->meta_txt,      $txt_url );
        update_post_meta( $pid, $this->meta_decision, $decision );
        update_post_meta( $pid, $this->meta_reason,   $reason );

        if ( $industry_slug ) {
            wp_set_object_terms( $pid, $industry_slug, 'cv_industry', false );
        }
        if ( $spec_slugs ) {
            wp_set_object_terms( $pid, $spec_slugs, 'cv_specialization', false );
        }
        if ( $pos_slugs ) {
            wp_set_object_terms( $pid, $pos_slugs, 'cv_position', false );
        }
        if ( $val_slugs ) {
            wp_set_object_terms( $pid, $val_slugs, 'cv_personal_value', false );
        }

        echo '<div class="cv-validator-success">';
        echo '<p><strong>'.esc_html__( 'Decisión:', 'cv-validator-saas' ).'</strong> '.esc_html( ucfirst( $decision ) ).'</p>';
        echo '<p><strong>'.esc_html__( 'Razón:', 'cv-validator-saas' ).'</strong> '.nl2br( esc_html( $reason ) ).'</p>';
        echo '<p><a href="'.esc_url( get_edit_post_link( $pid ) ).'">'.esc_html__( 'Ver/Editar CV', 'cv-validator-saas' ).'</a></p>';
        echo '</div>';
    }
}

register_activation_hook( __FILE__, array( 'CV_Validator_SaaS_Plugin', 'activate' ) );
new CV_Validator_SaaS_Plugin();
