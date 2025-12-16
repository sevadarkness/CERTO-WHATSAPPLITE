<?php
declare(strict_types=1);

/**
 * Alabama Layout Footer
 * Footer comum para todas as páginas do painel
 */
?>
        </main> <!-- fecha .alabama-content -->
    </div> <!-- fecha .alabama-main-wrapper -->
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Alabama Navigation JS -->
    <script src="assets/js/navigation.js"></script>
    
    <?php if (isset($extra_js)): ?>
        <?php foreach ((array)$extra_js as $js_file): ?>
            <script src="<?php echo htmlspecialchars($js_file, ENT_QUOTES, 'UTF-8'); ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
