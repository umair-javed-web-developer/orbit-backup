
<?php
/**
 * Plugin Name:       Orbit Backup & Restore
 * Description:       A professional, selective WordPress backup and restore solution with a modern dashboard UI.
 * Version:           2.7.1
 * Author:            Umair Javed
 * Author URI:        https://webmicron.com
 * Company:           Webmicron
 * Text Domain:       orbit-backup
 */

defined( 'ABSPATH' ) || exit;

// ─── Constants ────────────────────────────────────────────────────────────────
define( 'ORBIT_BACKUP_VERSION',   '2.7.1' );
define( 'ORBIT_BACKUP_AUTHOR',    'Umair Javed' );
define( 'ORBIT_BACKUP_COMPANY',   'Webmicron' );
define( 'ORBIT_BACKUP_URL',       'https://webmicron.com' );
define( 'ORBIT_BACKUP_FILE',      __FILE__ );
define( 'ORBIT_BACKUP_DIR',       plugin_dir_path( __FILE__ ) );
define( 'ORBIT_BACKUP_BASE_URL',  plugin_dir_url( __FILE__ ) );
define( 'ORBIT_BACKUP_STORE_DIR', WP_CONTENT_DIR . '/orbit-backups/' );
define( 'ORBIT_BACKUP_STORE_URL', WP_CONTENT_URL . '/orbit-backups/' );

// ─── Autoload ─────────────────────────────────────────────────────────────────
require_once ORBIT_BACKUP_DIR . 'includes/class-orbit-engine.php';
require_once ORBIT_BACKUP_DIR . 'includes/class-orbit-ajax.php';

// ─── Activation ───────────────────────────────────────────────────────────────
register_activation_hook( __FILE__, 'orbit_backup_activate' );
function orbit_backup_activate() {
    if ( ! file_exists( ORBIT_BACKUP_STORE_DIR ) ) wp_mkdir_p( ORBIT_BACKUP_STORE_DIR );
    if ( false === get_option( 'orbit_backup_history' ) ) add_option( 'orbit_backup_history', array() );
    if ( false === get_option( 'orbit_backup_schedule' ) ) add_option( 'orbit_backup_schedule', array( 'enabled' => '0', 'frequency' => 'daily', 'retention' => '30' ) );
}

// ─── Deactivation ─────────────────────────────────────────────────────────────
register_deactivation_hook( __FILE__, 'orbit_backup_deactivate' );
function orbit_backup_deactivate() {
    wp_clear_scheduled_hook( 'orbit_backup_cron_event' );
}

// ─── Cron Events ──────────────────────────────────────────────────────────────
add_filter( 'cron_schedules', 'orbit_backup_custom_cron_intervals' );
function orbit_backup_custom_cron_intervals( $schedules ) {
    $schedules['weekly'] = array(
        'interval' => 604800,
        'display'  => __( 'Once Weekly', 'orbit-backup' )
    );
    $schedules['monthly'] = array(
        'interval' => 2635200,
        'display'  => __( 'Once Monthly', 'orbit-backup' )
    );
    return $schedules;
}

add_action( 'orbit_backup_cron_event', 'orbit_backup_run_scheduled_tasks' );
function orbit_backup_run_scheduled_tasks() {
    $sched = get_option( 'orbit_backup_schedule' );
    if ( empty( $sched['enabled'] ) || '1' !== $sched['enabled'] ) return;

    $engine = new Orbit_Backup_Engine();
    $options = array(
        'include_db'      => ! empty( $sched['include_db'] ),
        'include_plugins' => ! empty( $sched['include_plugins'] ),
        'include_themes'  => ! empty( $sched['include_themes'] ),
        'include_uploads' => ! empty( $sched['include_uploads'] ),
        'include_others'  => ! empty( $sched['include_others'] ),
        'label'           => 'Scheduled',
    );
    $engine->run( $options );

    $retention_days = (int) ( $sched['retention'] ?? 30 );
    $history        = Orbit_Backup_Engine::get_history();
    $now            = time();
    $to_delete      = array();

    foreach ( $history as $entry ) {
        $created_at = strtotime( $entry['created_at'] );
        $diff_days  = ( $now - $created_at ) / ( 24 * 3600 );
        if ( $diff_days > $retention_days ) {
            $to_delete[] = $entry['id'];
        }
    }

    foreach ( $to_delete as $id ) {
        Orbit_Backup_Engine::delete_entry( $id );
    }
}

// ─── Admin Menu ──────────────────────────────────────────────────────────────
add_action( 'admin_menu', 'orbit_backup_add_menu' );
function orbit_backup_add_menu() {
    add_menu_page( 'Orbit Backup', 'Orbit Backup', 'manage_options', 'orbit-backup', 'orbit_backup_render_page', 'dashicons-backup', 80 );
}

// ─── Enqueue Assets ──────────────────────────────────────────────────────────
add_action( 'admin_enqueue_scripts', 'orbit_backup_enqueue_assets' );
function orbit_backup_enqueue_assets( $hook ) {
    if ( 'toplevel_page_orbit-backup' !== $hook ) return;

    wp_enqueue_style( 'orbit-font', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap', array(), null );

    wp_register_style( 'orbit-core', false );
    wp_enqueue_style( 'orbit-core' );
    wp_add_inline_style( 'orbit-core', orbit_backup_get_css_v2_7_1() );

    wp_register_script( 'orbit-js', false, array( 'jquery' ), null, true );
    wp_enqueue_script( 'orbit-js' );
    wp_add_inline_script( 'orbit-js', orbit_backup_get_js_v2_7_1() );

    wp_localize_script( 'orbit-js', 'OrbitConfig', array(
        'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'orbit_backup_nonce' ),
        'history'  => Orbit_Backup_Engine::get_history(),
        'schedule' => get_option( 'orbit_backup_schedule' ),
        'zipOk'    => class_exists( 'ZipArchive' )
    ) );
}

function orbit_backup_get_css_v2_7_1() {
    return '
    html.wp-toolbar { background: #ffffff !important; }
    #wpcontent { padding-left: 0 !important; background: #ffffff !important; position: relative !important; }
    #wpbody-content { padding-bottom: 0 !important; }
    #wpfooter { display: none !important; }
    
    #orbit-app {
        --primary: #ff6500;
        --secondary: #341902;
        --bg: #ffffff;
        --grey: #f9f9f9;
        --border: #eeeeee;
        --muted: #999999;
        --accent-soft: rgba(255,101,0,0.06);
        
        display: block !important;
        width: 100% !important;
        min-height: 100vh !important;
        background: var(--bg) !important;
        color: var(--secondary) !important;
        font-family: "Inter", sans-serif !important;
        margin: 0 !important;
        padding: 40px !important;
        box-sizing: border-box !important;
        position: relative !important;
    }

    #orbit-app * { box-sizing: border-box !important; }

    /* OVERLAY & PROGRESS */
    .orb-overlay { position: absolute !important; top: 0 !important; left: 0 !important; width: 100% !important; height: 100% !important; background: rgba(255,255,255,0.98) !important; z-index: 1000 !important; display: none !important; align-items: center !important; justify-content: center !important; backdrop-filter: blur(4px); }
    .orb-overlay.active { display: flex !important; }

    .orb-progress-box { width: 100%; max-width: 480px !important; text-align: center !important; padding: 50px; background: #fff; border-radius: 24px; box-shadow: 0 20px 60px rgba(52,25,2,0.1); border: 1px solid var(--border); }
    .orb-progress-track { background: #e5e5e5 !important; height: 14px !important; border-radius: 10px !important; overflow: hidden !important; margin: 35px 0 !important; position: relative !important; box-shadow: inset 0 2px 4px rgba(0,0,0,0.05) !important; }
    #orb-pfill { display: block !important; height: 100% !important; background: var(--primary) !important; width: 0%; border-radius: 10px !important; transition: width 0.4s cubic-bezier(0.1, 0.7, 0.1, 1) !important; box-shadow: 0 0 15px rgba(255,101,0,0.4) !important; position: relative !important; z-index: 5 !important; }
    .orb-percent-val { font-size: 32px !important; font-weight: 800 !important; color: var(--primary) !important; margin: 0; letter-spacing: -0.5px; }

    /* STRUCTURE */
    .orb-header { display: flex !important; justify-content: space-between !important; align-items: center !important; margin-bottom: 45px !important; border-bottom: 1px solid var(--border) !important; padding-bottom: 30px !important; }
    .orb-logo-box { background: var(--secondary) !important; width: 48px !important; height: 48px !important; border-radius: 10px !important; display: flex !important; align-items: center !important; justify-content: center !important; box-shadow: 0 4px 12px rgba(52,25,2,0.2); }
    .orb-logo-box svg { stroke: var(--primary) !important; width: 26px; height: 26px; stroke-width: 2.5; }
    
    .orb-nav { display: flex !important; gap: 12px !important; margin-bottom: 40px !important; }
    .orb-tab { background: transparent !important; border: 1px solid transparent !important; padding: 12px 22px !important; border-radius: 10px !important; font-weight: 600 !important; color: var(--muted) !important; cursor: pointer !important; transition: 0.2s !important; position: relative !important; border: 1px solid var(--border) !important; }
    .orb-tab.active { background: var(--secondary) !important; color: #ffffff !important; border-color: var(--secondary) !important; }
    .orb-badge { position: absolute !important; top: -8px !important; right: -8px !important; background: var(--primary) !important; color: #fff !important; font-size: 11px !important; font-weight: 800 !important; padding: 3px 8px !important; border-radius: 12px !important; min-width: 20px !important; border: 2px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
    
    .orb-container { max-width: 1240px !important; margin: 0 auto !important; }
    .orb-grid { display: grid !important; grid-template-columns: 1fr 320px !important; gap: 35px !important; }
    .orb-card { background: #fff !important; border: 1px solid var(--border) !important; border-radius: 20px !important; padding: 40px !important; position: relative !important; }

    /* COMPONENTS SELECTOR */
    .orb-selectors-grid { display: grid !important; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)) !important; gap: 18px !important; margin: 30px 0 !important; }
    .orb-sel { border: 2px solid var(--border) !important; border-radius: 16px !important; padding: 30px !important; text-align: center; cursor: pointer; transition: 0.2s ease-out; background: #fff; position: relative; overflow: hidden; }
    .orb-sel:hover { border-color: var(--primary) !important; background: var(--accent-soft) !important; }
    .orb-sel.active { border-color: var(--primary) !important; background: var(--accent-soft) !important; transform: translateY(-2px); box-shadow: 0 8px 24px rgba(255,101,0,0.08); }
    
    .orb-sel svg { display: block; margin: 0 auto 15px auto; width: 38px; height: 38px; stroke: var(--muted); stroke-width: 1.5; transition: 0.25s; }
    .orb-sel.active svg { stroke: var(--primary); transform: scale(1.1); }
    .orb-sel h3 { margin: 0; font-size: 15px; font-weight: 600; color: var(--muted); transition: 0.25s; }
    .orb-sel.active h3 { color: var(--secondary); }

    /* CUSTOM SELECT - ULTRA BRANDED */
    .orb-custom-select { position: relative; width: 100%; user-select: none; }
    .orb-select-trigger { 
        background: #fff; 
        border: 2px solid var(--border); 
        border-radius: 12px; 
        padding: 14px 18px; 
        font-weight: 700; 
        font-size: 14px; 
        color: var(--secondary); 
        cursor: pointer; 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        transition: 0.2s all ease;
    }
    .orb-select-trigger:after { 
        content: ""; 
        width: 10px; height: 10px; 
        border-right: 3px solid var(--primary); 
        border-bottom: 3px solid var(--primary); 
        transform: rotate(45deg); 
        margin-top: -5px; 
        transition: 0.2s;
    }
    .orb-custom-select.open .orb-select-trigger { border-color: var(--primary); box-shadow: 0 0 0 4px var(--accent-soft); }
    .orb-custom-select.open .orb-select-trigger:after { transform: rotate(-135deg); margin-top: 5px; }

    .orb-options { 
        position: absolute; 
        top: calc(100% + 8px); 
        left: 0; right: 0; 
        background: #fff; 
        border: 1px solid var(--border); 
        border-radius: 12px; 
        overflow: hidden; 
        z-index: 100; 
        box-shadow: 0 15px 40px rgba(52,25,2,0.15);
        display: none;
        animation: orbFadeUp 0.2s ease-out;
    }
    @keyframes orbFadeUp { from { opacity:0; transform: translateY(10px); } to { opacity:1; transform: translateY(0); } }
    .orb-custom-select.open .orb-options { display: block; }

    .orb-opt { 
        padding: 14px 18px; 
        font-size: 14px; 
        font-weight: 600; 
        color: var(--secondary); 
        cursor: pointer; 
        transition: 0.2s;
    }
    .orb-opt:hover { background: var(--accent-soft); color: var(--primary); }
    .orb-opt.selected { background: var(--secondary); color: #fff; }

    /* SCHEDULE STATE MANAGEMENT */
    .orb-schedule-container { transition: 0.3s opacity ease, 0.3s filter ease; }
    .orb-schedule-container.disabled { 
        opacity: 0.45 !important; 
        pointer-events: none !important; 
        filter: grayscale(0.8);
        user-select: none;
    }
    .orb-schedule-container.disabled * { cursor: not-allowed !important; }

    /* TOGGLE SWITCH */
    .orb-toggle { display: inline-flex; align-items: center; cursor: pointer; position: relative; }
    .orb-toggle input { opacity: 0; width: 0; height: 0; }
    .orb-slider { width: 44px; height: 24px; background-color: #e5e5e5; border-radius: 34px; transition: .4s; position: relative; margin-right: 12px; }
    .orb-slider:before { content: ""; position: absolute; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; border-radius: 50%; transition: .4s; }
    .orb-toggle input:checked + .orb-slider { background-color: var(--primary); }
    .orb-toggle input:checked + .orb-slider:before { transform: translateX(20px); }

    /* BUTTONS */
    .orb-btn { 
        display: inline-flex !important; 
        align-items: center !important; 
        justify-content: center !important;
        background: var(--primary) !important; 
        color: #ffffff !important; 
        border: none !important; 
        border-radius: 12px !important; 
        padding: 16px 32px !important; 
        font-weight: 700 !important; 
        font-size: 15px !important;
        cursor: pointer !important; 
        transition: 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
        text-decoration: none !important;
        box-shadow: 0 10px 25px rgba(255,101,0,0.25) !important;
        text-transform: uppercase !important;
        letter-spacing: 0.03em !important;
    }
    .orb-btn:hover { background: var(--secondary) !important; transform: translateY(-3px) !important; box-shadow: 0 15px 35px rgba(52,25,2,0.25) !important; }
    .orb-btn:active { transform: translateY(-1px) !important; }
    .orb-btn:disabled { background: #e5e5e5 !important; color: #aaaaaa !important; cursor: not-allowed !important; transform: none !important; box-shadow: none !important; }

    /* TABLES */
    .orb-table { width: 100%; border-collapse: collapse; }
    .orb-table th { text-align: left; padding: 22px 18px; border-bottom: 2px solid var(--secondary); color: var(--secondary); font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; background: #fafafa; }
    .orb-table td { padding: 22px 18px; border-bottom: 1px solid var(--border); font-size: 14px; color: var(--secondary); }

    /* AUTHORSHIP FOOTER */
    .orb-footer { margin-top: 60px; padding-top: 30px; border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
    .orb-footer-brand { display: flex; align-items: center; gap: 10px; text-decoration: none !important; transition: 0.2s; }
    .orb-footer-brand:hover { opacity: 0.8; }
    .orb-footer-brand span { font-size: 12px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em; }
    .orb-footer-brand strong { font-size: 12px; font-weight: 800; color: var(--secondary); }

    /* ACTION BUTTONS */
    .orb-act-btn { padding: 10px 18px !important; border-radius: 8px !important; font-weight: 700 !important; font-size: 12px !important; cursor: pointer !important; border: 1px solid var(--border) !important; background: #ffffff !important; color: var(--secondary) !important; margin-left: 8px !important; transition: 0.2s !important; text-transform: uppercase !important; }
    .orb-act-btn:hover { border-color: var(--primary) !important; color: var(--primary) !important; background: var(--accent-soft) !important; }
    .orb-act-btn-del { color: #dc2626 !important; border-color: #fecaca !important; }
    .orb-act-btn-del:hover { background: #dc2626 !important; color: #ffffff !important; border-color: #dc2626 !important; }

    /* COMPONENT BADGES */
    .orb-comp-badge { display: inline-block !important; padding: 4px 10px !important; border-radius: 6px !important; font-size: 10px !important; font-weight: 800 !important; text-transform: uppercase !important; margin-right: 5px !important; margin-bottom: 5px !important; letter-spacing: 0.02em !important; }
    .orb-comp-badge.db { background: #e0f2fe !important; color: #0369a1 !important; }
    .orb-comp-badge.plugins { background: #fef3c7 !important; color: #92400e !important; }
    .orb-comp-badge.themes { background: #f3e8ff !important; color: #6b21a8 !important; }
    .orb-comp-badge.media { background: #dcfce7 !important; color: #166534 !important; }
    .orb-comp-badge.core { background: #f1f5f9 !important; color: #475569 !important; }

    /* RESTORE / IMPORT */
    .orb-restore-card { text-align: center !important; max-width: 800px !important; margin: 0 auto !important; }
    .orb-upload-area { 
        display: block !important;
        border: 3px dashed var(--border) !important; 
        border-radius: 20px !important; 
        padding: 60px 40px !important; 
        margin: 30px 0 !important; 
        transition: 0.3s !important; 
        cursor: pointer !important; 
        background: var(--grey) !important;
        text-align: center !important;
    }
    .orb-upload-area:hover, .orb-upload-area.dragover { border-color: var(--primary) !important; background: var(--accent-soft) !important; }
    .orb-upload-area svg { display: inline-block !important; width: 64px; height: 64px; stroke: var(--primary); stroke-width: 1.5; margin-bottom: 20px; }
    .orb-upload-area h2 { margin: 0 0 10px 0 !important; }
    .orb-upload-area p { margin: 0 !important; font-weight: 600; color: var(--muted); }

    /* TOAST & TYPO */
    .orb-toast { 
        position: fixed !important; 
        bottom: 40px !important; 
        right: 40px !important; 
        background: var(--secondary) !important; 
        color: #ffffff !important; 
        padding: 20px 35px !important; 
        border-radius: 15px !important; 
        z-index: 10000000 !important; 
        font-weight: 700 !important; 
        box-shadow: 0 15px 45px rgba(0,0,0,0.3) !important; 
        border-left: 6px solid var(--primary) !important; 
        display: none; 
        font-size: 15px; 
    }

    h1 { font-size: 28px !important; font-weight: 800 !important; margin: 0 !important; letter-spacing: -0.5px; }
    h2 { font-size: 22px !important; font-weight: 800 !important; margin-bottom: 12px; color: var(--secondary); }
    p { color: var(--muted) !important; font-size: 15px !important; line-height: 1.6; }
    .hidden { display: none !important; }
    ';
}

function orbit_backup_get_js_v2_7_1() {
    return '
    jQuery(document).ready(function($) {
        let isRunning = false;

        $(document).on("click", ".orb-tab", function() {
            if (isRunning) return;
            const t = $(this).data("tab");
            $(".orb-tab").removeClass("active");
            $(this).addClass("active");
            $(".orb-panel").addClass("hidden");
            $("#orb-panel-" + t).removeClass("hidden");
        });

        $(document).on("click", ".orb-sel", function() {
            if (isRunning) return;
            $(this).toggleClass("active");
        });

        $(document).on("change", "#orb-sched-enabled", function() {
            const isEnabled = $(this).is(":checked");
            $(".orb-schedule-container").toggleClass("disabled", !isEnabled);
        });

        // ── Custom Select Interaction ──
        $(document).on("click", ".orb-select-trigger", function(e) {
            e.stopPropagation();
            const $cs = $(this).closest(".orb-custom-select");
            $(".orb-custom-select").not($cs).removeClass("open");
            $cs.toggleClass("open");
        });

        $(document).on("click", ".orb-opt", function() {
            const val = $(this).data("value");
            const text = $(this).text();
            const $cs = $(this).closest(".orb-custom-select");
            $cs.find(".orb-select-trigger span").text(text);
            $cs.data("value", val);
            $cs.find(".orb-opt").removeClass("selected");
            $(this).addClass("selected");
            $cs.removeClass("open");
        });

        $(document).on("click", function() {
            $(".orb-custom-select").removeClass("open");
        });

        // ── Restore / Import Actions ──
        $(document).on("change", "#orb-file-input", function(e) {
            const file = e.target.files[0];
            if (file) handleFileSelection(file);
        });

        $("#orb-upload-zone").on("dragover", function(e) {
            e.preventDefault();
            $(this).addClass("dragover");
        }).on("dragleave drop", function(e) {
            e.preventDefault();
            $(this).removeClass("dragover");
            if (e.type === "drop") {
                const file = e.originalEvent.dataTransfer.files[0];
                if (file) handleFileSelection(file);
            }
        });

        function handleFileSelection(file) {
            if (!file.name.endsWith(".zip")) return alert("Only .zip archives are allowed.");
            $("#orb-file-summary").removeClass("hidden").find("span").text(file.name + " (" + formatBytes(file.size) + ")");
            $("#orb-verify-btn").prop("disabled", false);
        }

        $(document).on("click", "#orb-verify-btn", function() {
            showToast("Archive Verification Initiated...");
            // Verification logic placeholder
        });

        // ── Manual Backup ──
        $(document).on("click", "#orb-run-backup-btn", function(e) {
            if (isRunning) return;
            const comps = {};
            $("#orb-panel-create .orb-sel").each(function() { comps[$(this).data("component")] = $(this).hasClass("active") ? "1" : "0"; });
            if (!Object.values(comps).includes("1")) return alert("Please select components.");

            isRunning = true;
            $("#orb-run-backup-btn").prop("disabled", true);
            $("#orb-overlay").addClass("active");
            $("#orb-pfill").css("width", "0%");
            $("#orb-ov-percent").text("0%");

            const blocker = e => { e.preventDefault(); e.stopPropagation(); return false; };
            $(window).on("keydown keyup keypress", blocker);

            let p = 0;
            const timer = setInterval(() => {
                p = Math.min(p + 1, 97);
                $("#orb-pfill").css("width", p + "%");
                $("#orb-ov-percent").text(p + "%");
            }, 300);

            $.post(OrbitConfig.ajaxUrl, $.extend({ action: "orbit_run_backup", _wpnonce: OrbitConfig.nonce }, comps))
            .done(res => {
                clearInterval(timer);
                $("#orb-pfill").css("width", "100%");
                $("#orb-ov-percent").text("100%");
                setTimeout(() => {
                    $("#orb-overlay").removeClass("active");
                    $("#orb-run-backup-btn").prop("disabled", false);
                    $(window).off("keydown keyup keypress", blocker);
                    isRunning = false;
                    if (res.success) {
                        OrbitConfig.history.unshift(res.data.entry);
                        renderHistory();
                        showToast("Archive Created Successfully!");
                    } else alert(res.data.message);
                }, 800);
            })
            .fail(() => {
                clearInterval(timer);
                isRunning = false;
                $("#orb-overlay").removeClass("active");
                $(window).off("keydown keyup keypress", blocker);
                alert("Request Fail.");
            });
        });

        // ── Save Schedule ──
        $(document).on("click", "#orb-save-schedule-btn", function() {
            const $btn = $(this);
            const comps = {};
            $("#orb-panel-schedule .orb-sel").each(function() { comps[$(this).data("component")] = $(this).hasClass("active") ? "1" : "0"; });
            
            const data = $.extend({
                action: "orbit_save_schedule",
                _wpnonce: OrbitConfig.nonce,
                enabled: $("#orb-sched-enabled").is(":checked") ? "1" : "0",
                frequency: $("#orb-panel-schedule .orb-custom-select").first().data("value"),
                retention: $("#orb-panel-schedule .orb-custom-select").last().data("value")
            }, comps);

            $btn.prop("disabled", true).text("SAVING...");

            $.post(OrbitConfig.ajaxUrl, data)
            .done(res => {
                $btn.prop("disabled", false).text("SAVE SCHEDULE SETTINGS");
                if (res.success) showToast("Schedule Updated!");
                else alert(res.data.message);
            });
        });

        function renderHistory() {
            const $body = $("#orb-history-list");
            $body.empty();
            const count = OrbitConfig.history ? OrbitConfig.history.length : 0;
            $("#orb-history-badge").text(count).toggle(count > 0);

            let totalBytes = 0;

            if (!count) {
                $body.html("<tr><td colspan=\'3\' style=\'text-align:center; padding:70px; color:#bbb; font-weight:600;\'>No Archives Found.</td></tr>");
            } else {
                OrbitConfig.history.forEach(item => {
                    totalBytes += parseInt(item.size);
                    let badgeHtml = "";
                    const c = item.components || [];
                    if (c.includes("db")) badgeHtml += "<span class=\'orb-comp-badge db\'>Database</span>";
                    if (c.includes("plugins")) badgeHtml += "<span class=\'orb-comp-badge plugins\'>Plugins</span>";
                    if (c.includes("themes")) badgeHtml += "<span class=\'orb-comp-badge themes\'>Themes</span>";
                    if (c.includes("uploads")) badgeHtml += "<span class=\'orb-comp-badge media\'>Media</span>";
                    if (c.includes("others")) badgeHtml += "<span class=\'orb-comp-badge core\'>Core</span>";

                    const $tr = $("<tr>").append(
                        $("<td>").html("<strong>" + item.label + "</strong><br><small style=\'color:#999; font-size:11px\'>" + item.filename + "</small>"),
                        $("<td>").html("<div style=\'margin-bottom:8px; font-weight:700; color:var(--secondary)\'>" + formatBytes(item.size) + "</div>" + badgeHtml),
                        $("<td style=\'text-align:right\'>").html(
                            "<button class=\'orb-act-btn orb-btn-dl\' data-id=\'" + item.id + "\'>Download</button>" +
                            "<button class=\'orb-act-btn orb-act-btn-del orb-btn-del\' data-id=\'" + item.id + "\'>Delete</button>"
                        )
                    );
                    $body.append($tr);
                });
            }
            
            $("#orb-storage-usage").text(formatBytes(totalBytes));
        }

        function showToast(msg) {
            $("#orb-toast-msg").text(msg);
            $("#orb-toast").fadeIn(400).delay(4500).fadeOut(400);
        }

        function formatBytes(b) {
            if (b===0) return "0 B";
            const k=1024, d=["B","KB","MB","GB","TB"], i=Math.floor(Math.log(b)/Math.log(k));
            return parseFloat((b/Math.pow(k, i)).toFixed(2)) + " " + d[i];
        }

        renderHistory();

        $(document).on("click", ".orb-btn-dl", function() {
            const id = $(this).data("id");
            $.post(OrbitConfig.ajaxUrl, { action: "orbit_download_backup", backup_id: id, _wpnonce: OrbitConfig.nonce })
            .done(res => { if (res.success && res.data.download_url) window.location.href = res.data.download_url; });
        });

        $(document).on("click", ".orb-btn-del", function() {
            if (!confirm("Permanently delete recovery point?")) return;
            const $btn = $(this);
            const id = $btn.data("id");
            $btn.prop("disabled", true).text("...");
            $.post(OrbitConfig.ajaxUrl, { action: "orbit_delete_backup", backup_id: id, _wpnonce: OrbitConfig.nonce })
            .done(res => {
                if (res.success) {
                    OrbitConfig.history = OrbitConfig.history.filter(h => h.id !== id);
                    renderHistory();
                } else {
                    alert(res.data.message);
                    $btn.prop("disabled", false).text("Delete");
                }
            });
        });
    });
    ';
}

function orbit_backup_render_page() {
    include ORBIT_BACKUP_DIR . 'admin/admin-page.php';
}
