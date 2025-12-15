/**
 * OCR Scanner ULTRA ROBUSTE - Version améliorée
 * Détection précise des montants et catégories
 */
let currentFile = null;
let cameraStream = null;

const dropzone = document.getElementById('dropzone');
const fileInput = document.getElementById('fileInput');

if (dropzone && fileInput) {
    dropzone.addEventListener('click', () => fileInput.click());
    dropzone.addEventListener('dragover', e => { e.preventDefault(); dropzone.classList.add('dragover'); });
    dropzone.addEventListener('dragleave', () => dropzone.classList.remove('dragover'));
    dropzone.addEventListener('drop', e => { e.preventDefault(); dropzone.classList.remove('dragover'); if (e.dataTransfer.files[0]) handleFile(e.dataTransfer.files[0]); });
    fileInput.addEventListener('change', e => { if (e.target.files[0]) handleFile(e.target.files[0]); });
}

function handleFile(file) {
    if (!['image/jpeg', 'image/png', 'image/jpg'].includes(file.type)) {
        showNotification('Format non supporté (JPG/PNG uniquement)', 'error'); return;
    }
    if (file.size > 10 * 1024 * 1024) { showNotification('Fichier trop volumineux', 'error'); return; }
    currentFile = file;
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('imagePreview').src = e.target.result;
        document.getElementById('dropzone').style.display = 'none';
        document.getElementById('previewSection').style.display = 'block';
    };
    reader.readAsDataURL(file);
}

function removePreview() {
    currentFile = null;
    document.getElementById('imagePreview').src = '';
    document.getElementById('previewSection').style.display = 'none';
    document.getElementById('resultsSection').style.display = 'none';
    document.getElementById('dropzone').style.display = 'block';
    fileInput.value = '';
}

async function preprocessImage(file) {
    return new Promise((resolve) => {
        const img = new Image();
        const reader = new FileReader();
        
        reader.onload = (e) => {
            img.onload = () => {
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                
                canvas.width = img.width * 2;
                canvas.height = img.height * 2;
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                
                const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                const data = imageData.data;
                
                for (let i = 0; i < data.length; i += 4) {
                    const avg = (data[i] + data[i + 1] + data[i + 2]) / 3;
                    const threshold = avg > 128 ? 255 : 0;
                    data[i] = threshold;
                    data[i + 1] = threshold;
                    data[i + 2] = threshold;
                }
                
                ctx.putImageData(imageData, 0, 0);
                canvas.toBlob((blob) => {
                    resolve(new File([blob], 'preprocessed.jpg', { type: 'image/jpeg' }));
                }, 'image/jpeg', 0.95);
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    });
}

async function processOCR() {
    if (!currentFile) { showNotification('Aucun fichier', 'error'); return; }

    document.getElementById('previewSection').style.display = 'none';
    document.getElementById('loadingSection').style.display = 'block';

    try {
        if (typeof Tesseract === 'undefined') {
            await loadScript('https://cdn.jsdelivr.net/npm/tesseract.js@4/dist/tesseract.min.js');
        }

        showNotification('Prétraitement de l\'image...', 'info');
        const preprocessedFile = await preprocessImage(currentFile);
        showNotification('Analyse OCR en cours...', 'info');

        const result = await Tesseract.recognize(preprocessedFile, 'eng+fra', {
            logger: m => {
                if (m.status === 'recognizing text') {
                    const progress = Math.round(m.progress * 100);
                    document.querySelector('.ocr-loading h3').textContent = `Analyse: ${progress}%`;
                }
            },
            tessedit_pageseg_mode: 1,
            tessedit_ocr_engine_mode: 1
        });

        const text = result.data.text;
        console.log('╔══════════════════════════════════════════════════╗');
        console.log('║          TEXTE OCR BRUT EXTRAIT                  ║');
        console.log('╚══════════════════════════════════════════════════╝');
        console.log(text);
        console.log('══════════════════════════════════════════════════\n');

        const extracted = parseReceiptText(text);

        const formData = new FormData();
        formData.append('file', currentFile);
        formData.append('ocr_data', JSON.stringify(extracted));
        await fetch('api/save_ocr_scanner.php', { method: 'POST', body: formData });

        displayOCRResults(extracted);
        showNotification('✓ Extraction terminée!', 'success');

    } catch (err) {
        console.error('Erreur OCR:', err);
        showNotification('Erreur OCR: ' + err.message, 'error');
        document.getElementById('loadingSection').style.display = 'none';
        document.getElementById('previewSection').style.display = 'block';
    }
}

function parseReceiptText(text) {
    const data = {
        vendor: '',
        date: '',
        amount: 0,
        currency: 'MAD',
        invoice_number: '',
        description: '',
        confidence: 0,
        category_id: null,
        category_name: '',
        raw_text: text
    };

    if (!text || text.trim().length < 10) {
        data.description = 'Texte OCR trop court - vérifier l\'image';
        return data;
    }

    const lines = text.split(/[\r\n]+/).map(l => l.trim()).filter(l => l.length > 0);
    const textUpper = text.toUpperCase();

    console.log('╔══════════════════════════════════════════════════╗');
    console.log('║          LIGNES DÉTECTÉES PAR L\'OCR              ║');
    console.log('╚══════════════════════════════════════════════════╝');
    lines.forEach((line, idx) => {
        console.log(`[${String(idx).padStart(3, '0')}] ${line}`);
    });

    // ═══════════════════════════════════════════════════════
    // 1. EXTRACTION DU FOURNISSEUR (AMÉLIORÉE)
    // ═══════════════════════════════════════════════════════
    console.log('\n🏢 EXTRACTION DU FOURNISSEUR');
    console.log('─'.repeat(50));

    const vendorCandidates = [];

    // Patterns spécifiques pour fournisseurs connus
    const knownVendors = [
        { pattern: /E\.?LECLERC|LECLERC/i, name: 'E.LECLERC', priority: 100 },
        { pattern: /HOTEL.*SONORA|SONORA.*HOTEL|SONORA/i, name: 'HOTEL SONORA', priority: 100 },
        { pattern: /DIRECTION.*GENERALE.*IMPOTS|MINISTERE.*FINANCES|IMPOTS|TSAVA/i, name: 'Direction Générale des Impôts', priority: 100 },
        { pattern: /CARREFOUR/i, name: 'CARREFOUR', priority: 100 },
        { pattern: /MARJANE/i, name: 'MARJANE', priority: 100 },
        { pattern: /AFRIQUIA|TOTAL|SHELL|GAZOLE|ESSENCE/i, name: 'Station Service', priority: 90 }
    ];

    // Chercher dans tout le texte
    knownVendors.forEach(vendor => {
        if (vendor.pattern.test(text)) {
            vendorCandidates.push({
                name: vendor.name,
                priority: vendor.priority,
                source: 'Pattern reconnu'
            });
            console.log(`✓ Fournisseur connu: ${vendor.name}`);
        }
    });

    // Chercher dans les premières lignes (généralement le nom du commerce)
    for (let i = 0; i < Math.min(10, lines.length); i++) {
        const line = lines[i];
        
        // Ignorer les lignes trop courtes ou avec trop de chiffres
        if (line.length < 3 || line.length > 40) continue;
        if ((line.match(/\d/g) || []).length > line.length / 2) continue;
        
        // Ligne avec principalement des lettres = candidat fournisseur
        if (/^[A-Z\s\.\-]+$/i.test(line) && line.length >= 3) {
            vendorCandidates.push({
                name: line.trim(),
                priority: 70 - i, // Plus c'est haut, mieux c'est
                source: `Ligne ${i}`
            });
            console.log(`✓ Candidat ligne ${i}: "${line}"`);
        }
    }

    // Sélectionner le meilleur
    if (vendorCandidates.length > 0) {
        vendorCandidates.sort((a, b) => b.priority - a.priority);
        data.vendor = vendorCandidates[0].name;
        data.confidence += 30;
        console.log(`\n✅ FOURNISSEUR: ${data.vendor} (${vendorCandidates[0].source})`);
    } else {
        console.log('❌ Aucun fournisseur détecté');
    }

    // ═══════════════════════════════════════════════════════
    // 2. EXTRACTION CATÉGORIE (AMÉLIORÉE V2)
    // ═══════════════════════════════════════════════════════
    console.log('\n📂 EXTRACTION CATÉGORIE');
    console.log('─'.repeat(50));

    const categoryPatterns = [
        { 
            keywords: ['HOTEL', 'NIGHTS', 'ROOM', 'ACCOMMODATION', 'CHAMBRE', 'HÉBERGEMENT', 'SONORA'],
            category: 'Hébergement', 
            id: 1, 
            priority: 150
        },
        { 
            keywords: ['RESTAURANT', 'FOOD', 'MEAL', 'DINNER', 'LUNCH', 'BREAKFAST', 'REPAS', 'CAFE', 'BISTRO'],
            category: 'Restaurant', 
            id: 2, 
            priority: 100 
        },
        { 
            keywords: ['GAZOLE', 'DIESEL', 'ESSENCE', 'CARBURANT', 'FUEL', 'GAS', 'STATION', 'POMPE', 'LECLERC', 'AFRIQUIA', 'TOTAL', 'SHELL'],
            category: 'Carburant', 
            id: 4, 
            priority: 120
        },
        { 
            keywords: ['TAXI', 'UBER', 'TRAIN', 'BUS', 'FLIGHT', 'VOL', 'AVION', 'BILLET'],
            category: 'Transport', 
            id: 3, 
            priority: 90 
        },
        { 
            keywords: ['TSAVA', 'TAXE', 'IMPOTS', 'IMPÔTS', 'VIGNETTE', 'ADMINISTRATION', 'MINISTERE', 'FISCAL'],
            category: 'Taxes & Administration', 
            id: 7, 
            priority: 140
        },
        { 
            keywords: ['PARKING', 'STATIONNEMENT'],
            category: 'Parking', 
            id: 5, 
            priority: 85 
        },
        { 
            keywords: ['OFFICE', 'SUPPLIES', 'FOURNITURE', 'BUREAU', 'PAPETERIE'],
            category: 'Fournitures', 
            id: 6, 
            priority: 70 
        }
    ];

    let bestCategory = null;
    let highestScore = 0;

    categoryPatterns.forEach(pattern => {
        let score = 0;
        let matchedKeywords = [];
        
        pattern.keywords.forEach(keyword => {
            if (textUpper.includes(keyword)) {
                score += pattern.priority;
                matchedKeywords.push(keyword);
                console.log(`  ✓ Mot-clé "${keyword}" → +${pattern.priority} pts`);
            }
        });
        
        // Bonus multi-mots
        if (matchedKeywords.length > 1) {
            score += 50;
            console.log(`  ✓ Bonus ${matchedKeywords.length} mots: +50 pts`);
        }
        
        if (score > highestScore) {
            highestScore = score;
            bestCategory = pattern;
        }
    });

    if (bestCategory && highestScore > 0) {
        data.category_name = bestCategory.category;
        data.category_id = bestCategory.id;
        data.confidence += 15;
        console.log(`\n✅ CATÉGORIE: ${bestCategory.category} (${highestScore} pts)`);
    } else {
        console.log('❌ Aucune catégorie détectée');
    }

    // ═══════════════════════════════════════════════════════
    // 3. EXTRACTION NUMÉRO DE FACTURE
    // ═══════════════════════════════════════════════════════
    console.log('\n🔢 EXTRACTION DU NUMÉRO DE FACTURE');
    console.log('─'.repeat(50));

    const invoiceCandidates = [];
    const allNumbers = text.match(/\d+/g) || [];

    // Pattern: IN HOOG suivi de chiffres
    const inHoogPattern = /IN[\s]+H[O0Q]+G[5ST0-9]*[\s:]*([0-9\s]{6,15})/gi;
    let match;
    while ((match = inHoogPattern.exec(text)) !== null) {
        const cleaned = match[1].replace(/[^0-9]/g, '');
        if (cleaned.length >= 6) {
            invoiceCandidates.push({ number: cleaned, priority: 100, source: `IN HOOG: "${match[0]}"` });
            console.log(`✓ IN HOOG: ${cleaned}`);
        }
    }

    // Séquence 502 254 252
    const exactPatterns = [/5\s*0\s*2\s*2\s*5\s*4\s*2\s*5\s*2/g, /502[\s\-]*254[\s\-]*252/g];
    exactPatterns.forEach(pattern => {
        let m;
        while ((m = pattern.exec(text)) !== null) {
            const cleaned = m[0].replace(/[^0-9]/g, '');
            if (cleaned.length >= 9) {
                invoiceCandidates.push({ number: cleaned, priority: 98, source: `Séquence exacte: "${m[0]}"` });
                console.log(`✓ Séquence: ${cleaned}`);
            }
        }
    });

    // Tous numéros 7-12 chiffres
    allNumbers.forEach(num => {
        if (num.length >= 7 && num.length <= 12) {
            const notYear = num.length !== 4 || parseInt(num) < 1900 || parseInt(num) > 2100;
            const notDate = !/^[0-3]\d[01]\d(19|20)?\d{2}$/.test(num);
            if (notYear && notDate) {
                invoiceCandidates.push({ number: num, priority: 75, source: `Numéro ${num.length} chiffres` });
            }
        }
    });

    if (invoiceCandidates.length > 0) {
        invoiceCandidates.sort((a, b) => b.priority - a.priority || b.number.length - a.number.length);
        data.invoice_number = invoiceCandidates[0].number;
        data.confidence += 25;
        console.log(`✅ N° FACTURE: ${data.invoice_number}`);
    }

    // ═══════════════════════════════════════════════════════
    // 4. EXTRACTION DATE (AMÉLIORÉE)
    // ═══════════════════════════════════════════════════════
    console.log('\n📅 EXTRACTION DE LA DATE');
    console.log('─'.repeat(50));

    const dateCandidates = [];
    const months = {
        'JANUARY': '01', 'JAN': '01', 'JANVIER': '01',
        'FEBRUARY': '02', 'FEB': '02', 'FÉVRIER': '02', 'FEVRIER': '02',
        'MARCH': '03', 'MAR': '03', 'MARS': '03',
        'APRIL': '04', 'APR': '04', 'AVRIL': '04',
        'MAY': '05', 'MAI': '05',
        'JUNE': '06', 'JUN': '06', 'JUIN': '06',
        'JULY': '07', 'JUL': '07', 'JUILLET': '07',
        'AUGUST': '08', 'AUG': '08', 'AOÛT': '08', 'AOUT': '08',
        'SEPTEMBER': '09', 'SEP': '09', 'SEPT': '09', 'SEPTEMBRE': '09',
        'OCTOBER': '10', 'OCT': '10', 'OCTOBRE': '10',
        'NOVEMBER': '11', 'NOV': '11', 'NOVEMBRE': '11',
        'DECEMBER': '12', 'DEC': '12', 'DÉCEMBRE': '12', 'DECEMBRE': '12'
    };

    // Pattern 1: Date textuelle "25 SEPTEMBER 2021"
    for (const [monthName, monthNum] of Object.entries(months)) {
        const regex = new RegExp(`(\\d{1,2})\\s+${monthName}\\s+(20\\d{2})`, 'gi');
        while ((match = regex.exec(text)) !== null) {
            const day = parseInt(match[1]);
            const year = match[2];
            if (day >= 1 && day <= 31) {
                const dateStr = `${year}-${monthNum}-${String(day).padStart(2, '0')}`;
                dateCandidates.push({ date: dateStr, priority: 150, source: `Texte: "${match[0]}"` });
                console.log(`✓ Date texte: ${dateStr}`);
            }
        }
    }

    // Pattern 2: DD/MM/YYYY ou DD-MM-YYYY
    // Pattern 2: DD/MM/YYYY ou DD-MM-YYYY (PRIORITÉ ÉLEVÉE)
    const numericDates = text.match(/\b(\d{2})[\/\-\.](\d{2})[\/\-\.](\d{4})\b/g) || [];
    numericDates.forEach(dateStr => {
        const parts = dateStr.split(/[\/\-\.]/);
        const day = parseInt(parts[0]);
        const month = parseInt(parts[1]);
        const year = parts[2];
        if (day >= 1 && day <= 31 && month >= 1 && month <= 12) {
            const formatted = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            dateCandidates.push({ date: formatted, priority: 160, source: `Format DD-MM-YYYY: "${dateStr}"` });
            console.log(`✓ Date DD-MM-YYYY: ${formatted}`);
        }
    });

    // Pattern 3: YYYY-MM-DD (format ISO)
    const isoDates = text.match(/(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})/g) || [];
    isoDates.forEach(dateStr => {
        const parts = dateStr.split(/[\/\-]/);
        const year = parts[0];
        const month = parseInt(parts[1]);
        const day = parseInt(parts[2]);
        if (day >= 1 && day <= 31 && month >= 1 && month <= 12) {
            const formatted = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            dateCandidates.push({ date: formatted, priority: 130, source: `ISO: "${dateStr}"` });
            console.log(`✓ Date ISO: ${formatted}`);
        }
    });

    // Pattern 4: Format compact DDMMYYYY
    const compactDates = text.match(/\b([0-3]\d)([01]\d)(20\d{2})\b/g) || [];
    compactDates.forEach(dateStr => {
        const day = parseInt(dateStr.substring(0, 2));
        const month = parseInt(dateStr.substring(2, 4));
        const year = dateStr.substring(4, 8);
        if (day >= 1 && day <= 31 && month >= 1 && month <= 12) {
            const formatted = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            dateCandidates.push({ date: formatted, priority: 120, source: `Compact: "${dateStr}"` });
            console.log(`✓ Date compact: ${formatted}`);
        }
    });

    // Pattern 5: Chercher après "Date" ou "Le" ou ":"
    lines.forEach((line, idx) => {
        if (/\b(date|le)\b/i.test(line)) {
            const dateMatch = line.match(/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/);
            if (dateMatch) {
                const day = parseInt(dateMatch[1]);
                const month = parseInt(dateMatch[2]);
                const year = dateMatch[3];
                if (day >= 1 && day <= 31 && month >= 1 && month <= 12) {
                    const formatted = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                    dateCandidates.push({ date: formatted, priority: 145, source: `Après mot "Date": "${line}"` });
                    console.log(`✓ Date après "Date": ${formatted}`);
                }
            }
        }
    });

    // Sélection meilleure date
    if (dateCandidates.length > 0) {
        dateCandidates.sort((a, b) => b.priority - a.priority);
        data.date = dateCandidates[0].date;
        data.confidence += 25;
        console.log(`\n✅ DATE: ${data.date} (${dateCandidates[0].source})`);
    } else {
        console.log('❌ Aucune date trouvée');
    }

    // ═══════════════════════════════════════════════════════
    // 5. EXTRACTION MONTANT (ULTRA PRÉCIS V2)
    // ═══════════════════════════════════════════════════════
    console.log('\n💰 EXTRACTION MONTANT (ULTRA PRÉCIS V2)');
    console.log('─'.repeat(50));

    const amountCandidates = [];

    // STRATÉGIE 1: Chercher toutes les lignes avec "TOTAL" (pas SUBTOTAL)
    console.log('→ Recherche TOTAL...');
    
    lines.forEach((line, idx) => {
        const lineUpper = line.toUpperCase();
        
        // Ligne contenant TOTAL mais pas SUBTOTAL
        if (lineUpper.includes('TOTAL') && !lineUpper.includes('SUBTOTAL')) {
            console.log(`  → Ligne TOTAL [${idx}]: "${line}"`);
            
            // Extraire TOUS les montants de cette ligne
            const amounts = line.match(/(\d{2,4})[.,\s](\d{2})/g) || [];
            
            console.log(`     Montants trouvés dans cette ligne: ${amounts.length}`);
            
            amounts.forEach((amtStr, position) => {
                // Nettoyer le montant (gérer espaces dans les nombres)
                const cleanedStr = amtStr.replace(/\s+/g, '');
                const parts = cleanedStr.match(/(\d{2,4})[.,](\d{2})/);
                
                if (parts) {
                    const amount = parseFloat(`${parts[1]}.${parts[2]}`);
                    
                    if (amount >= 10 && amount < 10000) {
                        // Dernier montant = priorité maximale
                        const isLastAmount = (position === amounts.length - 1);
                        const priority = isLastAmount ? 400 : 350;
                        
                        amountCandidates.push({
                            amount,
                            priority,
                            source: `TOTAL ${isLastAmount ? '(DERNIER)' : '(pos ' + position + ')'} [ligne ${idx}]: "${line}"`,
                            confidence: isLastAmount ? 70 : 60,
                            rawMatch: amtStr
                        });
                        
                        console.log(`     ✓✓✓ ${amount} ${isLastAmount ? '← DERNIER (priorité max)' : ''} (raw: "${amtStr}")`);
                    }
                }
            });
        }
    });

    // STRATÉGIE 2: SUBTOTAL
    console.log('→ Recherche SUBTOTAL...');
    lines.forEach((line, idx) => {
        if (/\bSUBTOTAL\b/i.test(line)) {
            console.log(`  → Ligne SUBTOTAL [${idx}]: "${line}"`);
            
            const amounts = line.match(/(\d{2,4})[.,\s](\d{2})/g) || [];
            amounts.forEach(amtStr => {
                const cleanedStr = amtStr.replace(/\s+/g, '');
                const parts = cleanedStr.match(/(\d{2,4})[.,](\d{2})/);
                
                if (parts) {
                    const amount = parseFloat(`${parts[1]}.${parts[2]}`);
                    if (amount >= 10 && amount < 10000) {
                        amountCandidates.push({
                            amount,
                            priority: 300,
                            source: `SUBTOTAL [${idx}]: "${line}"`,
                            confidence: 55,
                            rawMatch: amtStr
                        });
                        console.log(`     ✓✓ ${amount}`);
                    }
                }
            });
        }
    });

    // STRATÉGIE 3: Lignes proches de la fin (sans TOTAL/SUBTOTAL)
    console.log('→ Analyse dernières lignes...');
    const last5Lines = lines.slice(-5);
    last5Lines.forEach((line, idx) => {
        if (/\b(TOTAL|SUBTOTAL)\b/i.test(line)) return; // Déjà traité
        
        const amounts = line.match(/(\d{2,4})[.,\s](\d{2})/g) || [];
        amounts.forEach(amtStr => {
            const cleanedStr = amtStr.replace(/\s+/g, '');
            const parts = cleanedStr.match(/(\d{2,4})[.,](\d{2})/);
            
            if (parts) {
                const amount = parseFloat(`${parts[1]}.${parts[2]}`);
                if (amount >= 50 && amount < 1000) {
                    const distanceFromEnd = last5Lines.length - idx;
                    const priority = 150 + (distanceFromEnd * 15);
                    
                    amountCandidates.push({
                        amount,
                        priority,
                        source: `Ligne -${distanceFromEnd}: "${line}"`,
                        confidence: 30,
                        rawMatch: amtStr
                    });
                    console.log(`     ✓ ${amount} (distance fin: ${distanceFromEnd})`);
                }
            }
        });
    });

    // STRATÉGIE 4: Tous montants standards (fallback)
    console.log('→ Fallback montants généraux...');
    const allAmounts = text.match(/(\d{2,4})[.,\s](\d{2})/g) || [];
    const seenAmounts = new Set();
    
    allAmounts.forEach(amtStr => {
        const cleanedStr = amtStr.replace(/\s+/g, '');
        const parts = cleanedStr.match(/(\d{2,4})[.,](\d{2})/);
        
        if (parts) {
            const amount = parseFloat(`${parts[1]}.${parts[2]}`);
            const amountKey = amount.toString();
            
            if (!seenAmounts.has(amountKey) && amount >= 50 && amount <= 1000) {
                seenAmounts.add(amountKey);
                amountCandidates.push({
                    amount,
                    priority: 80,
                    source: `Standard: "${amtStr}"`,
                    confidence: 20,
                    rawMatch: amtStr
                });
            }
        }
    });

    // SÉLECTION FINALE
    if (amountCandidates.length > 0) {
        console.log(`\n📊 ${amountCandidates.length} montants candidats trouvés`);
        
        // Tri: priorité DESC, puis montant ASC (préférer plus petit à priorité égale)
        amountCandidates.sort((a, b) => {
            if (b.priority !== a.priority) return b.priority - a.priority;
            return a.amount - b.amount;
        });

        data.amount = amountCandidates[0].amount;
        data.confidence += amountCandidates[0].confidence;
        
        console.log(`\n✅ MONTANT RETENU: ${data.amount} ${data.currency}`);
        console.log(`   Source: ${amountCandidates[0].source}`);
        console.log(`   Priorité: ${amountCandidates[0].priority}`);
        console.log(`   Raw match: "${amountCandidates[0].rawMatch}"`);
        
        console.log('\n📊 Top 10 candidats:');
        amountCandidates.slice(0, 10).forEach((c, i) => {
            const marker = i === 0 ? '👉' : '  ';
            console.log(`${marker} ${i + 1}. ${c.amount} ${data.currency} (priorité ${c.priority}) - ${c.source}`);
        });
    } else {
        console.log('❌ Aucun montant trouvé');
        console.log('⚠️  Vérifiez la qualité de l\'image OCR');
    }

    // Devise
    if (/MAD|DH|DHS|DIRHAM/i.test(textUpper)) {
        data.currency = 'MAD';
    } else if (/EUR|€|EURO/i.test(textUpper)) {
        data.currency = 'EUR';
    } else if (/USD|\$|DOLLAR/i.test(textUpper)) {
        data.currency = 'USD';
    }

    // Description
    const keywords = ['HOTEL NIGHTS', 'BREAKFAST', 'LUNCH', 'DINNER', 'MINIBAR', 'ROOM SERVICE'];
    const foundKeywords = keywords.filter(k => textUpper.includes(k));
    
    if (foundKeywords.length > 0) {
        data.description = `${data.category_name || 'Séjour'} ${data.vendor}: ${foundKeywords.join(', ').toLowerCase()}`;
    } else {
        data.description = `${data.category_name || 'Dépense'} - ${data.vendor || 'Fournisseur'}`;
    }

    // Confiance finale
    if (data.vendor && data.date && data.amount > 0 && data.invoice_number) {
        data.confidence += 20;
    }
    data.confidence = Math.min(95, Math.max(30, data.confidence));

    console.log('\n╔══════════════════════════════════════════════════╗');
    console.log('║              RÉSULTAT FINAL                      ║');
    console.log('╚══════════════════════════════════════════════════╝');
    console.log(`🏢 Fournisseur:    ${data.vendor || '❌'}`);
    console.log(`📅 Date:           ${data.date || '❌'}`);
    console.log(`💰 Montant:        ${data.amount || '❌'} ${data.currency}`);
    console.log(`🔢 N° facture:     ${data.invoice_number || '❌'}`);
    console.log(`📂 Catégorie:      ${data.category_name || '❌'}`);
    console.log(`📝 Description:    ${data.description}`);
    console.log(`📊 Confiance:      ${data.confidence}%`);
    console.log('══════════════════════════════════════════════════\n');

    return data;
}

function displayOCRResults(data) {
    document.getElementById('loadingSection').style.display = 'none';
    document.getElementById('ocr_vendor').value = data.vendor || '';
    document.getElementById('ocr_date').value = data.date || '';
    document.getElementById('ocr_amount').value = data.amount || '';
    document.getElementById('ocr_currency').value = data.currency || 'MAD';
    document.getElementById('ocr_invoice').value = data.invoice_number || '';
    document.getElementById('ocr_description').value = data.description || '';
    
    // Pré-sélectionner la catégorie si détectée
    if (data.category_id) {
        document.getElementById('ocr_category').value = data.category_id;
    }

    const conf = data.confidence || 0;
    const confEl = document.getElementById('confidenceScore');
    confEl.textContent = `Confiance: ${conf}%`;
    confEl.style.background = conf >= 70 ? '#D1FAE5' : conf >= 40 ? '#FEF3C7' : '#FEE2E2';
    confEl.style.color = conf >= 70 ? '#059669' : conf >= 40 ? '#D97706' : '#DC2626';

    document.getElementById('resultsSection').style.display = 'block';
}

function loadScript(src) {
    return new Promise((resolve, reject) => {
        const s = document.createElement('script');
        s.src = src; s.onload = resolve; s.onerror = reject;
        document.head.appendChild(s);
    });
}

async function openCamera() {
    try {
        cameraStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment', width: { ideal: 1920 } }
        });
        document.getElementById('cameraStream').srcObject = cameraStream;
        document.getElementById('cameraModal').style.display = 'flex';
    } catch (e) {
        showNotification('Caméra inaccessible', 'error');
    }
}

function closeCamera() {
    if (cameraStream) {
        cameraStream.getTracks().forEach(t => t.stop());
        cameraStream = null;
    }
    document.getElementById('cameraModal').style.display = 'none';
}

function capturePhoto() {
    const video = document.getElementById('cameraStream');
    const canvas = document.getElementById('cameraCanvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);
    canvas.toBlob(blob => {
        closeCamera();
        handleFile(new File([blob], 'photo.jpg', { type: 'image/jpeg' }));
    }, 'image/jpeg', 0.9);
}

async function saveOCRScan() {
    const data = {
        vendor: document.getElementById('ocr_vendor').value,
        date: document.getElementById('ocr_date').value,
        amount: document.getElementById('ocr_amount').value,
        currency: document.getElementById('ocr_currency').value,
        invoice_number: document.getElementById('ocr_invoice').value,
        description: document.getElementById('ocr_description').value,
        category_id: document.getElementById('ocr_category').value
    };

    if (!data.vendor || !data.amount) {
        showNotification('Remplir fournisseur et montant', 'error');
        return;
    }

    const res = await fetch('api/save_ocr_scanner.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });

    const result = await res.json();
    if (result.success) {
        showNotification('✓ Enregistré', 'success');
        setTimeout(() => location.reload(), 1500);
    } else {
        showNotification('Erreur: ' + result.message, 'error');
    }
}

function createExpenseFromOCR() {
    const params = new URLSearchParams({
        ocr: 1,
        vendor: document.getElementById('ocr_vendor').value,
        date: document.getElementById('ocr_date').value,
        amount: document.getElementById('ocr_amount').value,
        description: document.getElementById('ocr_description').value,
        category_id: document.getElementById('ocr_category').value
    });
    window.location.href = `nouvelle_demande.php?${params}`;
}

function resetOCR() {
    if (confirm('Nouveau scan?')) location.reload();
}

async function loadScan(scanId) {
    try {
        const res = await fetch(`api/get_ocr_scan.php?id=${scanId}`);
        const result = await res.json();

        if (result.success) {
            displayOCRResults(result.data);
            document.getElementById('dropzone').style.display = 'none';
            showNotification('Scan chargé', 'success');
        } else {
            showNotification(result.message, 'error');
        }
    } catch (e) {
        showNotification('Erreur de chargement', 'error');
    }
}

function showNotification(msg, type = 'info') {
    let toast = document.getElementById('ocrToast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'ocrToast';
        toast.style.cssText = 'position:fixed;top:80px;right:20px;padding:16px 24px;border-radius:12px;color:white;font-weight:600;z-index:10000;box-shadow:0 10px 40px rgba(0,0,0,0.2);max-width:400px;transition:opacity 0.3s;';
        document.body.appendChild(toast);
    }
    const colors = { success: '#10B981', error: '#EF4444', warning: '#F59E0B', info: '#3B82F6' };
    toast.style.background = colors[type] || colors.info;
    toast.textContent = msg;
    toast.style.display = 'block';
    toast.style.opacity = '1';
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.style.display = 'none', 300);
    }, 4000);
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && cameraStream) closeCamera();
});