<?php
/**
 * Vardi Kit Settings Page Template.
 * This template uses the server-side $active_tab variable to render the correct content.
 * It is the standard, reliable WordPress method for creating tabbed settings pages.
 */

// Note: The $active_tab variable is passed to this file from the render_settings_page() function.

$tabs = [
        'general'     => 'عمومی',
        'appearance'  => 'ظاهری',
        'security'    => 'امنیتی',
        'performance' => 'کارایی و سئو',
        'access'      => 'دسترسی',
];
?>
<div class="wrap vardi-kit-admin-wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <p>به پنل مدیریت افزونه اختصاصی Vardi Kit خوش آمدید. از طریق تب‌های زیر می‌توانید تمام قابلیت‌ها را مدیریت کنید.</p>

    <h2 class="nav-tab-wrapper">
        <?php foreach ($tabs as $slug => $title) : ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=vardi-site-management&tab=' . $slug)); ?>"
               class="nav-tab <?php echo $active_tab === $slug ? 'nav-tab-active' : ''; ?>">
                <?php echo esc_html($title); ?>
            </a>
        <?php endforeach; ?>
    </h2>

    <form action="options.php" method="post">
        <?php
        settings_fields('vardi_kit_settings_group');

        // **FIX**: یک فیلد مخفی اضافه می‌کنیم تا به تابع sanitize اطلاع دهیم کدام تب فعال بوده است
        // این کار برای مدیریت صحیح چک‌باکس‌های تیک‌نخورده حیاتی است
        ?>
        <input type="hidden" name="vardi_kit_active_tab_marker" value="<?php echo esc_attr($active_tab); ?>" />
        <?php

        // This structure uses a PHP switch statement to ensure only the content
        // for the currently active tab is rendered in the HTML.
        ?>
        <div class="tab-content-wrapper">
            <?php
            switch ($active_tab) {
                case 'general':
                    do_settings_sections('vardi_general');
                    break;
                case 'appearance':
                    do_settings_sections('vardi_appearance');
                    break;
                case 'security':
                    do_settings_sections('vardi_security');
                    break;
                case 'performance':
                    do_settings_sections('vardi_performance');
                    break;
                case 'access':
                    do_settings_sections('vardi_access');
                    break;
            }
            ?>
        </div>

        <?php submit_button('ذخیره تغییرات'); ?>
    </form>
</div>