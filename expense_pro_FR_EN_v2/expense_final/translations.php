<?php
/**
 * Système de traduction multilingue - ExpensePro
 * Langues supportées : Français (défaut), Arabe, Anglais
 */

class Translation {
    private $lang;
    private $translations = [];
    
    public function __construct($lang = 'fr') {
        $this->lang = $lang;
        $this->loadTranslations();
    }
    
    private function loadTranslations() {
        $this->translations = [
            // Navigation
            'dashboard' => [
                'fr' => 'Tableau de bord',
                'ar' => 'لوحة القيادة',
                'en' => 'Dashboard'
            ],
            'expenses' => [
                'fr' => 'Notes de frais',
                'ar' => 'مصاريف',
                'en' => 'Expenses'
            ],
            'new_expense' => [
                'fr' => 'Nouvelle note de frais',
                'ar' => 'مصروف جديد',
                'en' => 'New Expense'
            ],
            'my_expenses' => [
                'fr' => 'Mes notes de frais',
                'ar' => 'مصاريفي',
                'en' => 'My Expenses'
            ],
            'pending_approval' => [
                'fr' => 'En attente d\'approbation',
                'ar' => 'في انتظار الموافقة',
                'en' => 'Pending Approval'
            ],
            
            // OCR Section
            'scan_receipt' => [
                'fr' => 'Scanner le reçu',
                'ar' => 'مسح الإيصال',
                'en' => 'Scan Receipt'
            ],
            'upload_photo' => [
                'fr' => 'Télécharger une photo',
                'ar' => 'تحميل صورة',
                'en' => 'Upload Photo'
            ],
            'ocr_processing' => [
                'fr' => 'Traitement en cours...',
                'ar' => 'جاري المعالجة...',
                'en' => 'Processing...'
            ],
            'auto_extracted' => [
                'fr' => 'Données extraites automatiquement',
                'ar' => 'البيانات المستخرجة تلقائياً',
                'en' => 'Auto-extracted data'
            ],
            
            // Formulaire
            'amount' => [
                'fr' => 'Montant',
                'ar' => 'المبلغ',
                'en' => 'Amount'
            ],
            'date' => [
                'fr' => 'Date',
                'ar' => 'التاريخ',
                'en' => 'Date'
            ],
            'category' => [
                'fr' => 'Catégorie',
                'ar' => 'الفئة',
                'en' => 'Category'
            ],
            'description' => [
                'fr' => 'Description',
                'ar' => 'الوصف',
                'en' => 'Description'
            ],
            'receipt' => [
                'fr' => 'Reçu',
                'ar' => 'الإيصال',
                'en' => 'Receipt'
            ],
            
            // Catégories
            'transport' => [
                'fr' => 'Transport',
                'ar' => 'النقل',
                'en' => 'Transport'
            ],
            'meal' => [
                'fr' => 'Repas',
                'ar' => 'وجبة',
                'en' => 'Meal'
            ],
            'accommodation' => [
                'fr' => 'Hébergement',
                'ar' => 'الإقامة',
                'en' => 'Accommodation'
            ],
            'office_supplies' => [
                'fr' => 'Fournitures bureau',
                'ar' => 'مستلزمات المكتب',
                'en' => 'Office Supplies'
            ],
            'other' => [
                'fr' => 'Autre',
                'ar' => 'أخرى',
                'en' => 'Other'
            ],
            
            // Actions
            'submit' => [
                'fr' => 'Soumettre',
                'ar' => 'إرسال',
                'en' => 'Submit'
            ],
            'save' => [
                'fr' => 'Enregistrer',
                'ar' => 'حفظ',
                'en' => 'Save'
            ],
            'cancel' => [
                'fr' => 'Annuler',
                'ar' => 'إلغاء',
                'en' => 'Cancel'
            ],
            'approve' => [
                'fr' => 'Approuver',
                'ar' => 'الموافقة',
                'en' => 'Approve'
            ],
            'reject' => [
                'fr' => 'Rejeter',
                'ar' => 'رفض',
                'en' => 'Reject'
            ],
            
            // Statuts
            'pending' => [
                'fr' => 'En attente',
                'ar' => 'قيد الانتظار',
                'en' => 'Pending'
            ],
            'approved' => [
                'fr' => 'Approuvé',
                'ar' => 'موافق عليه',
                'en' => 'Approved'
            ],
            'rejected' => [
                'fr' => 'Rejeté',
                'ar' => 'مرفوض',
                'en' => 'Rejected'
            ],
            
            // Messages
            'success_submit' => [
                'fr' => 'Note de frais soumise avec succès',
                'ar' => 'تم إرسال المصروف بنجاح',
                'en' => 'Expense submitted successfully'
            ],
            'ocr_success' => [
                'fr' => 'Reçu scanné avec succès',
                'ar' => 'تم مسح الإيصال بنجاح',
                'en' => 'Receipt scanned successfully'
            ],
            'error_upload' => [
                'fr' => 'Erreur lors du téléchargement',
                'ar' => 'خطأ في التحميل',
                'en' => 'Upload error'
            ],
            
            // Monnaie
            'currency' => [
                'fr' => 'DH',
                'ar' => 'درهم',
                'en' => 'MAD'
            ]
        ];
    }
    
    public function get($key) {
        if (isset($this->translations[$key][$this->lang])) {
            return $this->translations[$key][$this->lang];
        }
        return $key; // Retourne la clé si traduction non trouvée
    }
    
    public function setLang($lang) {
        $this->lang = $lang;
    }
    
    public function getCurrentLang() {
        return $this->lang;
    }
}

// Helper function
function __($key) {
    global $translator;
    return $translator->get($key);
}
?>