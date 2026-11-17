<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France\Lifecycle;

/**
 * Reason given with a status (MDT-113), the closed list of rule BR-FR-CDV-CL-09.
 *
 * Labels come from annex 7 "Tableau des motifs de refus", and for the REJ_* and IRR_* codes from
 * the DSE lists of rejection and inadmissibility reasons; label() is null for the codes no
 * available source labels.
 */
enum StatusReason: string
{
    case RETRAIT_MAN_SERV = 'RETRAIT_MAN_SERV';
    case ST_CT_NON_DECLAR = 'ST_CT_NON_DECLAR';
    case SUPPR_COMP_AVOIR = 'SUPPR_COMP_AVOIR';
    case TRANSF_PMNT_REGIE = 'TRANSF_PMNT_REGIE';
    case CONTACT_ACHTR = 'CONTACT_ACHTR';
    case NON_TRANSMISE = 'NON_TRANSMISE';
    case JUSTIF_ABS = 'JUSTIF_ABS';
    case ROUTAGE_ERR = 'ROUTAGE_ERR';
    case AUTRE = 'AUTRE';
    case COORD_BANC_ERR = 'COORD_BANC_ERR';
    case TX_TVA_ERR = 'TX_TVA_ERR';
    case MONTANTTOTAL_ERR = 'MONTANTTOTAL_ERR';
    case CALCUL_ERR = 'CALCUL_ERR';
    case NON_CONFORME = 'NON_CONFORME';
    case DOUBLON = 'DOUBLON';
    case DEST_INC = 'DEST_INC';
    case DEST_ERR = 'DEST_ERR';
    case TRANSAC_INC = 'TRANSAC_INC';
    case EMMET_INC = 'EMMET_INC';
    case CONTRAT_TERM = 'CONTRAT_TERM';
    case DOUBLE_FACT = 'DOUBLE_FACT';
    case CMD_ERR = 'CMD_ERR';
    case ADR_ERR = 'ADR_ERR';
    case SIRET_ERR = 'SIRET_ERR';
    case CODE_ROUTAGE_ERR = 'CODE_ROUTAGE_ERR';
    case REF_CT_ABSENT = 'REF_CT_ABSENT';
    case REF_ERR = 'REF_ERR';
    case PU_ERR = 'PU_ERR';
    case REM_ERR = 'REM_ERR';
    case QTE_ERR = 'QTE_ERR';
    case ART_ERR = 'ART_ERR';
    case MODPAI_ERR = 'MODPAI_ERR';
    case QUALITE_ERR = 'QUALITE_ERR';
    case LIVR_INCOMP = 'LIVR_INCOMP';
    case REJ_SEMAN = 'REJ_SEMAN';
    case REJ_UNI = 'REJ_UNI';
    case REJ_COH = 'REJ_COH';
    case REJ_ADR = 'REJ_ADR';
    case REJ_CONT_B2G = 'REJ_CONT_B2G';
    case REJ_REF_PJ = 'REJ_REF_PJ';
    case REJ_ASS_PJ = 'REJ_ASS_PJ';
    case IRR_VIDE_F = 'IRR_VIDE_F';
    case IRR_TYPE_F = 'IRR_TYPE_F';
    case IRR_SYNTAX = 'IRR_SYNTAX';
    case IRR_TAILLE_PJ = 'IRR_TAILLE_PJ';
    case IRR_NOM_PJ = 'IRR_NOM_PJ';
    case IRR_VID_PJ = 'IRR_VID_PJ';
    case IRR_EXT_DOC = 'IRR_EXT_DOC';
    case IRR_TAILLE_F = 'IRR_TAILLE_F';
    case IRR_ANTIVIRUS = 'IRR_ANTIVIRUS';
    case IRR_NOM_F = 'IRR_NOM_F';

    /**
     * The French label, or null when no available source labels the code.
     */
    public function label(): ?string
    {
        return match ($this) {
            self::ST_CT_NON_DECLAR => 'Sous-traitant / cotraitant non déclaré',
            self::SUPPR_COMP_AVOIR => 'Suppression pour compensation d\'avoirs',
            self::TRANSF_PMNT_REGIE => 'Transfert pour paiement en régie (Réservé B2G)',
            self::CONTACT_ACHTR => 'Autres : contacter votre acheteur',
            self::JUSTIF_ABS => 'Justificatif absent ou insuffisant',
            self::ROUTAGE_ERR => 'Erreur de routage',
            self::AUTRE => 'Autre',
            self::COORD_BANC_ERR => 'Erreur de coordonnées bancaires',
            self::TX_TVA_ERR => 'Taux de TVA erroné',
            self::MONTANTTOTAL_ERR => 'Montant Total Erroné',
            self::CALCUL_ERR => 'Erreur de calcul de la facture',
            self::NON_CONFORME => 'Mention légale manquante',
            self::DOUBLON => 'Facture en doublon (déjà émise / réçue)',
            self::DEST_INC => 'Destinataire inconnu',
            self::DEST_ERR => 'Erreur de destinataire',
            self::TRANSAC_INC => 'Transaction inconnue',
            self::EMMET_INC => 'Emetteur inconnu',
            self::CONTRAT_TERM => 'Contrat terminé',
            self::DOUBLE_FACT => 'Données réglementaire F1 en doublon',
            self::ADR_ERR => 'L\'adresse de facturation électronique erronée',
            self::SIRET_ERR => 'SIRET Erroné ou absent',
            self::CODE_ROUTAGE_ERR => 'CODE_ROUTAGE Absent ou Erroné',
            self::REF_CT_ABSENT => 'Référence contractuelle nécessaire pour le traitement de la facture manquante',
            self::REF_ERR => 'Référence incorrecte',
            self::PU_ERR => 'Prix Unitaires incorrects',
            self::REM_ERR => 'Remise erronée',
            self::QTE_ERR => 'Quantité facturée incorrecte',
            self::ART_ERR => 'Article facturé incorrect',
            self::MODPAI_ERR => 'Modalités de paiement incorrectes',
            self::QUALITE_ERR => 'Qualité d\'article livré incorrecte',
            self::LIVR_INCOMP => 'Livraison incomplète / non effectuée',
            self::REJ_SEMAN => 'Contrôle du format sémantique',
            self::REJ_UNI => 'Contrôle d’unicité',
            self::REJ_COH => 'Contrôle de cohérence des données',
            self::IRR_VIDE_F => 'Contrôle des fichiers non vides',
            self::IRR_TYPE_F => 'Contrôle de type et extension des fichiers',
            self::IRR_SYNTAX => 'Contrôle syntaxique des fichiers',
            self::IRR_TAILLE_PJ => 'Contrôle de taille des pièces jointes',
            self::IRR_NOM_PJ => 'Contrôle du nom des pièces jointes',
            self::IRR_VID_PJ => 'Contrôle des pièces jointes non vides',
            self::IRR_EXT_DOC => 'Contrôle de type et extension des pièces jointes',
            self::IRR_TAILLE_F => 'Contrôle de taille des fichiers',
            self::IRR_ANTIVIRUS => 'Contrôle anti-virus',
            self::IRR_NOM_F => 'Contrôle du nom des fichiers',
            self::RETRAIT_MAN_SERV, self::NON_TRANSMISE, self::CMD_ERR, self::REJ_ADR, self::REJ_CONT_B2G, self::REJ_REF_PJ, self::REJ_ASS_PJ => null,
        };
    }
}
