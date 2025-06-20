<?php
// admin/includes/footer_admin.php
?>
    <footer style="text-align: center; padding: 20px; margin-top: 30px; background-color: #ecf0f1; border-top: 1px solid #dcdcdc;">
        <p>&copy; <?php echo date('Y'); ?> <?php echo defined('SITE_NAME') ? SITE_NAME : 'Catalogo Topolino'; ?> - Pannello di Amministrazione</p>
    </footer>
</body>
</html>
<?php
ob_end_flush(); // <<--- AGGIUNTO: Invia l'output bufferizzato e termina il buffering
?>