<?php
/**
 * Created by PhpStorm.
 * User: mcasiro
 * Date: 2018-05-31
 * Time: 16:20
 */

namespace RW\ACH;
use InvalidArgumentException;


/**
 * Class EntryDetailRecord
 *
 * @package RW\ACH
 */
class EntryDetailRecord extends FileComponent
{
    /* FIXED VALUES */
    public const FIXED_RECORD_TYPE_CODE = '6';
    /* DEFAULT VALUES */
    private const DEFAULT_ADDENDA_INDICATOR = '0';
    /* VARIABLE VALUE FIELD NAMES */
    public const TRANSACTION_CODE   = 'TRANSACTION_CODE';
    public const TRANSIT_ABA_NUMBER = 'TRANSIT_ABA_NUMBER';
    public const CHECK_DIGIT        = 'CHECK_DIGIT';
    public const DFI_ACCOUNT_NUMBER = 'DFI_ACCOUNT_NUMBER';
    public const AMOUNT             = 'AMOUNT';
    public const ID_NUMBER          = 'ID_NUMBER';
    public const INDIVIDUAL_NAME    = 'INDIVIDUAL_NAME';
    public const DRAFT_INDICATOR    = 'DRAFT_INDICATOR';
    public const ADDENDA_INDICATOR  = 'ADDENDA_INDICATOR';
    public const TRACE_NUMBER       = 'TRACE_NUMBER';

    protected const REQUIRED_FIELDS = [
        self::TRANSACTION_CODE,
        self::TRANSIT_ABA_NUMBER,
        self::DFI_ACCOUNT_NUMBER,
        self::AMOUNT,
        self::INDIVIDUAL_NAME,
        self::TRACE_NUMBER,
    ];

    private const OPTIONAL_FIELDS = [
        self::ID_NUMBER         => null,
        self::DRAFT_INDICATOR   => null,
        self::ADDENDA_INDICATOR => null,
    ];

    /* TRANSACTION CODES */
    public const CHECKING_CREDIT_RETURN_OR_NOC = '21';
    public const CHECKING_CREDIT_DEPOSIT       = '22';
    public const CHECKING_CREDIT_PRE_NOTE      = '23';
    public const CHECKING_CREDIT_ZERO_DOLLAR   = '24';
    public const CHECKING_DEBIT_RETURN_OR_NOC  = '26';
    public const CHECKING_DEBIT_PAYMENT        = '27';
    public const CHECKING_DEBIT_PRE_NOTE       = '28';
    public const CHECKING_DEBIT_ZERO_DOLLAR    = '29';

    public const SAVINGS_CREDIT_RETURN_OR_NOC = '31';
    public const SAVINGS_CREDIT_DEPOSIT       = '32';
    public const SAVINGS_CREDIT_PRE_NOTE      = '33';
    public const SAVINGS_CREDIT_ZERO_DOLLAR   = '34';
    public const SAVINGS_DEBIT_RETURN_OR_NOC  = '36';
    public const SAVINGS_DEBIT_PAYMENT        = '37';
    public const SAVINGS_DEBIT_PRE_NOTE       = '38';
    public const SAVINGS_DEBIT_ZERO_DOLLAR    = '39';

    public const GENERAL_LEDGER_CREDIT_RETURN_OR_NOC = '41';
    public const GENERAL_LEDGER_CREDIT               = '42';
    public const GENERAL_LEDGER_CREDIT_PRENOTE       = '43';
    public const GENERAL_LEDGER_ZERO_DOLLAR_CREDIT   = '44';  // With remittance data (SEC code of CCD and CTX only)
    public const GENERAL_LEDGER_DEBIT_RETURN_OR_NOC  = '46';
    public const GENERAL_LEDGER_DEBIT                = '47';
    public const GENERAL_LEDGER_DEBIT_PRENOTE        = '48';
    public const GENERAL_LEDGER_ZERO_DOLLAR_DEBIT    = '49';  // With remittance data (SEC code of CCD and CTX only)

    public const LOAN_ACCOUNT_CREDIT_RETURN_OR_NOC = '51';
    public const LOAN_ACCOUNT_CREDIT               = '52';
    public const LOAN_ACCOUNT_CREDIT_PRENOTE       = '53';
    public const LOAN_ACCOUNT_ZERO_DOLLAR_CREDIT   = '54';  // With remittance data (SEC code of CCD and CTX only)
    public const LOAN_ACCOUNT_DEBIT                = '55';  // Reversal only
    public const LOAN_ACCOUNT_DEBIT_RETURN_OR_NOC  = '56';

    public const CREDIT_TRANSACTION_CODES = [
        self::CHECKING_CREDIT_DEPOSIT,
        self::CHECKING_CREDIT_PRE_NOTE,
        self::CHECKING_CREDIT_ZERO_DOLLAR,
        self::SAVINGS_CREDIT_DEPOSIT,
        self::SAVINGS_CREDIT_PRE_NOTE,
        self::SAVINGS_CREDIT_ZERO_DOLLAR,
    ];
    public const DEBIT_TRANSACTION_CODES  = [
        self::CHECKING_DEBIT_PAYMENT,
        self::CHECKING_DEBIT_PRE_NOTE,
        self::CHECKING_DEBIT_ZERO_DOLLAR,
        self::SAVINGS_DEBIT_PAYMENT,
        self::SAVINGS_DEBIT_PRE_NOTE,
        self::SAVINGS_DEBIT_ZERO_DOLLAR,
    ];

    private const CHECK_DIGIT_WEIGHTS = [3, 7, 1, 3, 7, 1, 3, 7];

    /** @var int */
    private $entryDetailSequenceNumber;
    /** @var AddendaRecord */
    private $addendaRecord;

    /**
     * Build an Entry Detail record from an existing string.
     *
     * @param string $input 94 character fixed-width ACH Record string
     * @return EntryDetailRecord
     * @throws ValidationException
     */
    public static function buildFromString($input): EntryDetailRecord
    {
        $buildData = self::getBuildDataFromInputString($input);

        // Extract the sequence number from the last 7 digits
        $sequenceNumber = (int) substr($input, (94 - 7));

        return new EntryDetailRecord($buildData, $sequenceNumber, false);
    }

    /**
     * Generate the field specifications for each field in the file component.
     * Format is an array of arrays as follows:
     *  $this->fieldSpecifications = [
     *      FIELD_NAME => [
     *          self::FIELD_INCLUSION => Mandatory, Required, or Optional (reserved for future use)
     *          self::VALIDATOR       => array: [
     *              Validation type (self::VALIDATOR_REGEX or self::VALIDATOR_DATE_TIME)
     *              Validation string (regular expression or date-time format)
     *          ]
     *          self::LENGTH          => Required if 'PADDING' is provided: Fixed width of the field
     *          self::POSITION_START  => Starting position within the component (reserved for future use)
     *          self::POSITION_END    => Ending position within the component (reserved for future use)
     *          self::PADDING         => Optional: self::ALPHANUMERIC_PADDING or self::NUMERIC_PADDING
     *          self::CONTENT         => The content to be output for this field
     *      ],
     *      ...
     *  ]
     */
    protected static function getFieldSpecifications(): array
    {
        $validTransactionCodes = array_merge(self::DEBIT_TRANSACTION_CODES, self::CREDIT_TRANSACTION_CODES);

        return [
            self::RECORD_TYPE_CODE   => [
                self::FIELD_INCLUSION => self::FIELD_INCLUSION_MANDATORY,
                self::VALIDATOR       => [self::VALIDATOR_REGEX, '/^\d{1}$/'],
                self::LENGTH          => 1,
                self::POSITION_START  => 1,
                self::POSITION_END    => 1,
                self::CONTENT         => self::FIXED_RECORD_TYPE_CODE,
            ],
            self::TRANSACTION_CODE   => [
                self::FIELD_INCLUSION => self::FIELD_INCLUSION_MANDATORY,
                self::VALIDATOR       => [self::VALIDATOR_ARRAY, $validTransactionCodes],
                self::LENGTH          => 2,
                self::POSITION_START  => 2,
                self::POSITION_END    => 3,
                self::CONTENT         => null,
            ],
            self::TRANSIT_ABA_NUMBER => [
                self::FIELD_INCLUSION => self::FIELD_INCLUSION_MANDATORY,
                self::VALIDATOR       => [self::VALIDATOR_REGEX, '/^\d{1,8}$/'],
                self::LENGTH          => 8,
                self::POSITION_START  => 4,
                self::POSITION_END    => 11,
                // We need to pad these to make sure numbers with leading 0's aren't truncated
                self::PADDING         => self::NUMERIC_PADDING,
                self::CONTENT         => '',
            ],
            self::CHECK_DIGIT        => [
                self::FIELD_INCLUSION => self::FIELD_INCLUSION_MANDATORY,
                self::VALIDATOR       => [self::VALIDATOR_REGEX, '/^\d{1}$/'],
                self::LENGTH          => 1,
                self::POSITION_START  => 12,
                self::POSITION_END    => 12,
                self::CONTENT         => null,
            ],
            self::DFI_ACCOUNT_NUMBER => [
                self::FIELD_INCLUSION => self::FIELD_INCLUSION_REQUIRED,
                self::VALIDATOR       => [self::VALIDATOR_REGEX, '/^[-a-zA-Z0-9 ]{1,17}$/'],
                self::LENGTH          => 17,
                self::POSITION_START  => 13,
                self::POSITION_END    => 29,
                self::PADDING         => self::ALPHANUMERIC_PADDING,
                self::CONTENT         => null,
            ],
            self::AMOUNT             => [
                self::FIELD_INCLUSION => self::FIELD_INCLUSION_MANDATORY,
                self::VALIDATOR       => [self::VALIDATOR_REGEX, '/^\d{1,10}$/'],
                self::LENGTH          => 10,
                self::POSITION_START  => 30,
                self::POSITION_END    => 39,
                self::PADDING         => self::NUMERIC_PADDING,
                self::CONTENT         => null,
            ],
            self::ID_NUMBER          => [
                self::FIELD_INCLUSION => self::FIELD_INCLUSION_OPTIONAL,
                self::VALIDATOR       => [self::VALIDATOR_REGEX, '/^[-a-zA-Z0-9 ]{0,15}$/'],
                self::LENGTH          => 15,
                self::POSITION_START  => 40,
                self::POSITION_END    => 54,
                self::PADDING         => self::ALPHANUMERIC_PADDING,
                self::CONTENT         => null,
            ],
            self::INDIVIDUAL_NAME    => [
                self::FIELD_INCLUSION => self::FIELD_INCLUSION_REQUIRED,
                self::VALIDATOR       => [self::VALIDATOR_REGEX, '/^[a-zA-Z0-9 ]{1,22}$/'],
                self::LENGTH          => 22,
                self::POSITION_START  => 55,
                self::POSITION_END    => 76,
                self::PADDING         => self::ALPHANUMERIC_PADDING,
                self::CONTENT         => null,
            ],
            self::DRAFT_INDICATOR    => [
                self::FIELD_INCLUSION => self::FIELD_INCLUSION_OPTIONAL,
                self::VALIDATOR       => [self::VALIDATOR_REGEX, '/^[a-zA-Z0-9 *?]{0,2}$/'],
                self::LENGTH          => 2,
                self::POSITION_START  => 77,
                self::POSITION_END    => 78,
                self::PADDING         => self::ALPHANUMERIC_PADDING,
                self::CONTENT         => null,
            ],
            self::ADDENDA_INDICATOR  => [
                self::FIELD_INCLUSION => self::FIELD_INCLUSION_MANDATORY,
                self::VALIDATOR       => [self::VALIDATOR_REGEX, '/^[01]$/'],
                self::LENGTH          => 1,
                self::POSITION_START  => 79,
                self::POSITION_END    => 79,
                self::CONTENT         => null,
            ],
            self::TRACE_NUMBER       => [
                self::FIELD_INCLUSION => self::FIELD_INCLUSION_MANDATORY,
                self::VALIDATOR       => [self::VALIDATOR_REGEX, '/^\d{15}$/'],
                self::LENGTH          => 15,
                self::POSITION_START  => 80,
                self::POSITION_END    => 94,
                self::CONTENT         => null,
            ],
        ];
    }

    /**
     * Returns true if an addenda record is included/expected, otherwise returns false.
     *
     * @return bool
     */
    public function hasAddendaRecord(): bool
    {
        return (
            $this->fieldSpecifications[self::ADDENDA_INDICATOR][self::CONTENT] === '1'
            || $this->addendaRecord !== null
        );
    }

    /**
     * Returns the Addenda record, if one exists.
     *
     * @return AddendaRecord|null
     */
    public function getAddendaRecord(): AddendaRecord
    {
        return $this->addendaRecord;
    }

    /**
     * Set the Addenda record.
     *
     * @param $v
     * @return EntryDetailRecord
     */
    public function setAddendaRecord($v): EntryDetailRecord
    {
        $this->addendaRecord = $v;
        $this->fieldSpecifications[self::ADDENDA_INDICATOR][self::CONTENT] = '1';

        return $this;
    }

    /**
     * Get the string representation of the Entry Detail record. If there is an attached Addenda record,
     * the string will contain two lines.
     * @return string
     */
    public function toString()
    {
        $addendaString = $this->hasAddendaRecord() ? "\n{$this->addendaRecord->toString()}" : '';

        return parent::toString() . $addendaString;
    }

    /**
     * EntryDetailRecord constructor.
     *
     * @param array $fields   is an array of field key => value pairs as follows:
     *                        [
     *                            // Required
     *                            TRANSACTION_CODE   => Designates the type of transaction, use class constants
     *                            TRANSIT_ABA_NUMBER => The nine digit transit/ABA number for the target account
     *                            DFI_ACCOUNT_NUMBER => The (up to) 17 digit account number for the target account
     *                            AMOUNT             => The amount to be transferred
     *                            INDIVIDUAL_NAME    => The individual or business who owns the account
     *                            TRACE_NUMBER       => Must be the ORIGINATING_DFI_ID from the Batch Header
     *                            // Optional
     *                            ID_NUMBER          => May be used to insert number for internal purposes
     *                            DRAFT_INDICATOR    => Codes to enable special handling of the entry
     *                            ADDENDA_INDICATOR  => 1 is addenda record is included, 0 if not
     *                        ]
     * @param int   $sequence
     * @param bool  $validate
     * @throws ValidationException
     */
    public function __construct(array $fields, $sequence, $validate = true)
    {
        if (!is_array($fields)) {
            throw new InvalidArgumentException('fields argument must be of type array.');
        }

        // Check for required fields
        $missing_fields = array_diff(self::REQUIRED_FIELDS, array_keys($fields));
        if ($missing_fields) {
            throw new InvalidArgumentException('Cannot create ' . self::class . ' without all required fields, missing: ' . implode(', ', $missing_fields));
        }

        $this->entryDetailSequenceNumber = $sequence;

        if ($validate) {
            // Add any missing optional fields, but preserve user-provided values for those that exist
            $fields = array_merge(self::OPTIONAL_FIELDS, $fields);
            $fields = $this->getModifiedFields($fields, $sequence);
        }

        parent::__construct($fields, $validate);
    }

    /**
     * @param $fields
     * @param $sequence
     * @return array
     * @throws ValidationException
     */
    protected function getModifiedFields($fields, $sequence): array
    {
        // Extract the last digit from the transit number and use it as the check digit
        if (!is_numeric($fields[self::TRANSIT_ABA_NUMBER]) || strlen($fields[self::TRANSIT_ABA_NUMBER]) !== 9) {
            throw new ValidationException("Invalid transit number {$fields[self::TRANSIT_ABA_NUMBER]}, non numeric or incorrect length.");
        }
        // Can't use math because conversion to an integer truncates leading zeros
        $transitNumber = $fields[self::TRANSIT_ABA_NUMBER];
        $fields[self::TRANSIT_ABA_NUMBER] = mb_substr($transitNumber, 0, 8);
        $fields[self::CHECK_DIGIT]        = mb_substr($transitNumber, 8);
        if (!$this->isValidCheckDigit($fields[self::TRANSIT_ABA_NUMBER], $fields[self::CHECK_DIGIT])) {
            throw new ValidationException("Invalid transit number {$transitNumber}, check digit does not match.");
        }

        // We can't work with amounts that aren't numeric, so add special validation here to prevent
        // bcmul from silently converting bad inputs to zero
        if (!is_numeric($fields[self::AMOUNT])) {
            throw new ValidationException('Value: "' . ($fields[self::AMOUNT] ?? 'null') . '" for "' . self::AMOUNT . '" must be numeric');
        }
        // Move decimal over and dump extra digits
        $fields[self::AMOUNT] = bcmul($fields[self::AMOUNT], '100', 0);

        // Concatenate the provided immediate destination, and the left-padded sequence number
        $fields[self::TRACE_NUMBER] = $fields[self::TRACE_NUMBER] . str_pad($sequence, 7, '0', STR_PAD_LEFT);

        // Use the default addenda indicator if none was provided
        $fields[self::ADDENDA_INDICATOR] = $fields[self::ADDENDA_INDICATOR] ?: self::DEFAULT_ADDENDA_INDICATOR;

        return $fields;
    }

    private function isValidCheckDigit($transitNumber, $checkDigit)
    {
        // Not concerned about truncating leading zeros for this process
        $transitNumber = (int) $transitNumber;
        $transitSum = 0;
        foreach (array_reverse(self::CHECK_DIGIT_WEIGHTS) as $weight) {
            $digit         = $transitNumber % 10;
            $transitSum    += $digit * $weight;
            $transitNumber = (int) ($transitNumber / 10);
        }

        $calculatedCheckDigit = (10 - ($transitSum % 10)) % 10; // Final mod lets 10 - 0 = 0

        return $checkDigit == $calculatedCheckDigit;
    }
}
