<?php
/**
 * Système OCR pour extraction automatique des données des reçus
 * Utilise Tesseract OCR via API
 */

class ReceiptOCR {
    private $apiKey;
    private $apiUrl = 'https://api.ocr.space/parse/image'; // API gratuite OCR.space
    
    public function __construct() {
        // Clé API gratuite (remplacer par votre propre clé)
        $this->apiKey = 'K87899142388957'; // API Key gratuite de démonstration
    }
    
    /**
     * Scanner un reçu et extraire les données
     */
    public function scanReceipt($imagePath) {
        try {
            // Vérifier si le fichier existe
            if (!file_exists($imagePath)) {
                throw new Exception("Fichier image non trouvé");
            }
            
            // Préparer l'image pour l'OCR
            $imageData = base64_encode(file_get_contents($imagePath));
            
            // Appeler l'API OCR
            $ocrText = $this->performOCR($imageData);
            
            // Extraire les données du texte OCR
            $extractedData = $this->extractDataFromText($ocrText);
            
            return [
                'success' => true,
                'raw_text' => $ocrText,
                'extracted_data' => $extractedData
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Effectuer l'OCR via API
     */
    private function performOCR($imageBase64) {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'apikey' => $this->apiKey,
                'base64Image' => 'data:image/jpeg;base64,' . $imageBase64,
                'language' => 'fre', // Français + Arabe
                'isOverlayRequired' => false,
                'detectOrientation' => true,
                'scale' => true,
                'OCREngine' => 2 // Moteur OCR v2 plus performant
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("Erreur API OCR");
        }
        
        $result = json_decode($response, true);
        
        if (!$result['IsErroredOnProcessing']) {
            return $result['ParsedResults'][0]['ParsedText'];
        }
        
        throw new Exception("Erreur lors du traitement OCR");
    }
    
    /**
     * Extraire montant, date, catégorie du texte OCR
     */
    private function extractDataFromText($text) {
        $data = [
            'amount' => null,
            'date' => null,
            'category' => null,
            'merchant' => null,
            'confidence' => 0
        ];
        
        // Extraire le montant (DH, MAD, Dhs, درهم)
        $amount = $this->extractAmount($text);
        if ($amount) {
            $data['amount'] = $amount;
            $data['confidence'] += 30;
        }
        
        // Extraire la date
        $date = $this->extractDate($text);
        if ($date) {
            $data['date'] = $date;
            $data['confidence'] += 25;
        }
        
        // Détecter la catégorie
        $category = $this->detectCategory($text);
        if ($category) {
            $data['category'] = $category;
            $data['confidence'] += 20;
        }
        
        // Extraire le nom du commerçant
        $merchant = $this->extractMerchant($text);
        if ($merchant) {
            $data['merchant'] = $merchant;
            $data['confidence'] += 15;
        }
        
        return $data;
    }
    
    /**
     * Extraire le montant du texte
     */
    private function extractAmount($text) {
        // Patterns pour détecter les montants
        $patterns = [
            '/(\d+[,.]?\d*)\s*(DH|MAD|Dhs|درهم)/i',
            '/Total[:\s]+(\d+[,.]?\d*)/i',
            '/Montant[:\s]+(\d+[,.]?\d*)/i',
            '/TOTAL[:\s]+(\d+[,.]?\d*)/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                // Nettoyer le montant
                $amount = str_replace(',', '.', $matches[1]);
                return floatval($amount);
            }
        }
        
        return null;
    }
    
    /**
     * Extraire la date du texte
     */
    private function extractDate($text) {
        $patterns = [
            '/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/',  // DD/MM/YYYY
            '/(\d{2,4})[\/\-](\d{1,2})[\/\-](\d{1,2})/',  // YYYY/MM/DD
            '/Date[:\s]+(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return $this->normalizeDate($matches[0]);
            }
        }
        
        return null;
    }
    
    /**
     * Normaliser la date au format Y-m-d
     */
    private function normalizeDate($dateStr) {
        // Nettoyer la chaîne
        $dateStr = preg_replace('/Date[:\s]+/i', '', $dateStr);
        
        // Essayer différents formats
        $formats = ['d/m/Y', 'd-m-Y', 'Y/m/d', 'Y-m-d', 'd/m/y'];
        
        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $dateStr);
            if ($date) {
                return $date->format('Y-m-d');
            }
        }
        
        return date('Y-m-d');
    }
    
    /**
     * Détecter automatiquement la catégorie
     */
    private function detectCategory($text) {
        $categories = [
            'transport' => ['taxi', 'bus', 'train', 'uber', 'carburant', 'essence', 'parking', 'تاكسي', 'النقل'],
            'meal' => ['restaurant', 'café', 'pizza', 'burger', 'food', 'repas', 'مطعم', 'طعام'],
            'accommodation' => ['hotel', 'auberge', 'hébergement', 'فندق', 'الإقامة'],
            'office_supplies' => ['fourniture', 'papeterie', 'bureau', 'office', 'مكتب'],
            'communication' => ['téléphone', 'internet', 'mobile', 'هاتف']
        ];
        
        $textLower = mb_strtolower($text);
        
        foreach ($categories as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (mb_strpos($textLower, mb_strtolower($keyword)) !== false) {
                    return $category;
                }
            }
        }
        
        return 'other';
    }
    
    /**
     * Extraire le nom du commerçant (première ligne généralement)
     */
    private function extractMerchant($text) {
        $lines = explode("\n", $text);
        
        // Prendre la première ligne non vide
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line) && strlen($line) > 3 && strlen($line) < 50) {
                return $line;
            }
        }
        
        return null;
    }
    
    /**
     * Alternative OCR locale avec Tesseract (si installé sur le serveur)
     */
    public function scanWithTesseract($imagePath) {
        // Vérifier si Tesseract est installé
        exec('which tesseract', $output, $returnCode);
        
        if ($returnCode !== 0) {
            return ['success' => false, 'error' => 'Tesseract non installé'];
        }
        
        // Exécuter Tesseract
        $outputFile = sys_get_temp_dir() . '/ocr_' . uniqid();
        exec("tesseract '$imagePath' '$outputFile' -l fra+ara", $output, $returnCode);
        
        if ($returnCode === 0 && file_exists($outputFile . '.txt')) {
            $text = file_get_contents($outputFile . '.txt');
            unlink($outputFile . '.txt');
            
            $extractedData = $this->extractDataFromText($text);
            
            return [
                'success' => true,
                'raw_text' => $text,
                'extracted_data' => $extractedData
            ];
        }
        
        return ['success' => false, 'error' => 'Erreur Tesseract'];
    }
}
?>