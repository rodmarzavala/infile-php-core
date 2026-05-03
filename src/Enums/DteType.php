<?php

declare(strict_types=1);

namespace InfilePhp\Core\Enums;

/**
 * All supported DTE (Documento Tributario Electrónico) types in Guatemala's FEL system.
 */
enum DteType: string
{
    case Invoice = 'FACT';
    case InvoiceWithAmendment = 'FCAM';
    case SmallTaxpayer = 'FPEQ';
    case SmallTaxpayerWithAmendment = 'FCAP';
    case SpecialInvoice = 'FESP';
    case NonTaxableDocument = 'NABN';
    case DonationReceipt = 'RDON';
    case Receipt = 'RECI';
    case DebitNote = 'NDEB';
    case CreditNote = 'NCRE';

    /**
     * Return a human-readable label for the DTE type.
     */
    public function label(): string
    {
        return match($this) {
            self::Invoice => 'Standard Invoice',
            self::InvoiceWithAmendment => 'Invoice with Amendment',
            self::SmallTaxpayer => 'Small Taxpayer Invoice',
            self::SmallTaxpayerWithAmendment => 'Small Taxpayer Invoice with Amendment',
            self::SpecialInvoice => 'Special Invoice',
            self::NonTaxableDocument => 'Non-Taxable Document',
            self::DonationReceipt => 'Donation Receipt',
            self::Receipt => 'Receipt',
            self::DebitNote => 'Debit Note',
            self::CreditNote => 'Credit Note',
        };
    }
}
