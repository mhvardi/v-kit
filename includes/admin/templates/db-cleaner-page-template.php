<div class="wrap vardi-kit-admin-wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <p>از این بخش می‌توانید اطلاعات اضافی و غیرضروری را از دیتابیس سایت خود حذف کرده و به بهینه‌سازی و افزایش سرعت آن کمک کنید.</p>
    <p><strong>هشدار:</strong> قبل از انجام هر عملیات، اکیداً توصیه می‌شود یک نسخه پشتیبان کامل از دیتابیس خود تهیه کنید.</p>

    <?php if ( ! empty( $message ) ) echo $message; ?>

    <form method="post" action="">
        <?php wp_nonce_field( 'vardi_db_cleanup_nonce' ); ?>
        
        <table class="wp-list-table widefat striped" style="margin-top: 20px;">
            <thead>
                <tr>
                    <th style="width: 60%;"><strong>نوع بهینه‌سازی</strong></th>
                    <th><strong>تعداد موارد یافت شده</strong></th>
                    <th style="text-align: left;"><strong>عملیات</strong></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong>رونوشت نوشته‌ها</strong>
                        <p class="description">وردپرس به صورت خودکار نسخه‌های قدیمی مطالب شما را ذخیره می‌کند. حذف آن‌ها حجم دیتابیس را کاهش می‌دهد.</p>
                    </td>
                    <td><?php echo number_format_i18n( $stats['revisions'] ); ?></td>
                    <td style="text-align: left;">
                        <button type="submit" name="vardi_cleanup_action" value="revisions" class="button button-primary" <?php disabled( $stats['revisions'], 0 ); ?>>پاکسازی</button>
                    </td>
                </tr>
                <tr>
                    <td>
                        <strong>پیش‌نویس‌های خودکار</strong>
                        <p class="description">پیش‌نویس‌هایی که وردپرس به صورت خودکار ایجاد می‌کند اما هرگز استفاده نشده‌اند.</p>
                    </td>
                    <td><?php echo number_format_i18n( $stats['drafts'] ); ?></td>
                    <td style="text-align: left;">
                        <button type="submit" name="vardi_cleanup_action" value="drafts" class="button button-primary" <?php disabled( $stats['drafts'], 0 ); ?>>پاکسازی</button>
                    </td>
                </tr>
                <tr>
                    <td>
                        <strong>دیدگاه‌های جفنگ (اسپم)</strong>
                        <p class="description">حذف تمام دیدگاه‌هایی که به عنوان اسپم علامت‌گذاری شده‌اند.</p>
                    </td>
                    <td><?php echo number_format_i18n( $stats['spam_comments'] ); ?></td>
                    <td style="text-align: left;">
                        <button type="submit" name="vardi_cleanup_action" value="spam_comments" class="button button-primary" <?php disabled( $stats['spam_comments'], 0 ); ?>>پاکسازی</button>
                    </td>
                </tr>
                <tr>
                    <td>
                        <strong>دیدگاه‌های داخل زباله‌دان</strong>
                        <p class="description">حذف دائمی دیدگاه‌هایی که به زباله‌دان منتقل شده‌اند.</p>
                    </td>
                    <td><?php echo number_format_i18n( $stats['trashed_comments'] ); ?></td>
                    <td style="text-align: left;">
                        <button type="submit" name="vardi_cleanup_action" value="trashed_comments" class="button button-primary" <?php disabled( $stats['trashed_comments'], 0 ); ?>>پاکسازی</button>
                    </td>
                </tr>
                <tr>
                    <td>
                        <strong>اطلاعات موقت منقضی شده (Transients)</strong>
                        <p class="description">پاک کردن داده‌های کش منقضی شده که توسط افزونه‌ها و قالب‌ها در دیتابیس ذخیره می‌شوند.</p>
                    </td>
                    <td><?php echo number_format_i18n( $stats['transients'] ); ?></td>
                    <td style="text-align: left;">
                        <button type="submit" name="vardi_cleanup_action" value="transients" class="button button-primary" <?php disabled( $stats['transients'], 0 ); ?>>پاکسازی</button>
                    </td>
                </tr>
            </tbody>
        </table>
    </form>
</div>