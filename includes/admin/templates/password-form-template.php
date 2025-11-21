<div class="wrap vardi-kit-admin-wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <h2 class="nav-tab-wrapper">
        <a href="#general" class="nav-tab nav-tab-active">عمومی</a>
        <a href="#appearance" class="nav-tab">ظاهری</a>
        <a href="#security" class="nav-tab">امنیتی</a>
        <a href="#performance" class="nav-tab">کارایی و سئو</a>
        <a href="#access" class="nav-tab">دسترسی</a>
    </h2>

    <form action="options.php" method="post">
        <?php settings_fields('vardi_kit_settings_group'); ?>

        <div id="general" class="tab-content active">
            <?php do_settings_sections('vardi_general'); ?>
        </div>
        <div id="appearance" class="tab-content">
            <?php do_settings_sections('vardi_appearance'); ?>
        </div>
        <div id="security" class="tab-content">
            <?php do_settings_sections('vardi_security'); ?>
        </div>
        <div id="performance" class="tab-content">
            <?php do_settings_sections('vardi_performance'); ?>
        </div>
        <div id="access" class="tab-content">
            <?php do_settings_sections('vardi_access'); ?>
        </div>

        <?php submit_button('ذخیره تغییرات'); ?>
    </form>
</div>