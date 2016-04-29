<?php

namespace Nicebooks\Isbn;

use Nicebooks\Isbn\Exception\IsbnException;
use Nicebooks\Isbn\Internal\Formatter;

/**
 * Represents a valid ISBN number. This class is immutable.
 */
class Isbn
{
    /**
     * @var string
     */
    private $isbn;

    /**
     * @var boolean
     */
    private $is13;

    /**
     * The 4 or 5 parts of this ISBN number (depending on whether it's an ISBN-10 or ISBN-13).
     *
     * If this ISBN number is not in a recognized range, this property will be null.
     *
     * @var array|null
     */
    private $parts;

    /**
     * @param string     $isbn  The unformatted ISBN number, validated.
     * @param boolean    $is13  Whether this is an ISBN-13.
     * @param array|null $parts The parts of this ISBN number, or null if it's not in a recognized range.
     */
    private function __construct($isbn, $is13, array $parts = null)
    {
        $this->isbn  = $isbn;
        $this->is13  = $is13;
        $this->parts = $parts;
    }

    /**
     * @param string $isbn
     *
     * @return Isbn
     *
     * @throws Exception\InvalidIsbnException
     */
    public static function get($isbn)
    {
        $isbn = (string) $isbn;

        if (preg_match(Internal\Regexp::ASCII, $isbn) === 0) {
            throw Exception\InvalidIsbnException::forIsbn($isbn);
        }

        $isbn = preg_replace(Internal\Regexp::NON_ALNUM, '', $isbn);

        if (preg_match(Internal\Regexp::ISBN13, $isbn) === 1) {
            if (! Internal\CheckDigit::validateCheckDigit13($isbn)) {
                throw Exception\InvalidIsbnException::forIsbn($isbn);
            }

            return new Isbn($isbn, true, Formatter::getParts($isbn));
        }

        $isbn = strtoupper($isbn);

        if (preg_match(Internal\Regexp::ISBN10, $isbn) === 1) {
            if (! Internal\CheckDigit::validateCheckDigit10($isbn)) {
                throw Exception\InvalidIsbnException::forIsbn($isbn);
            }

            return new Isbn($isbn, false, Formatter::getParts($isbn));
        }

        throw Exception\InvalidIsbnException::forIsbn($isbn);
    }

    /**
     * @return boolean
     */
    public function is10()
    {
        return ! $this->is13;
    }

    /**
     * @return boolean
     */
    public function is13()
    {
        return $this->is13;
    }

    /**
     * @return boolean
     */
    public function isConvertibleTo10()
    {
        if ($this->is13) {
            return $this->parts[0] === '978';
        }

        return true;
    }

    /**
     * Returns a copy of this Isbn, converted to ISBN-10.
     *
     * @return Isbn The ISBN-10.
     *
     * @throws Exception\IsbnNotConvertibleException If this is an ISBN-13 not starting with 978.
     */
    public function to10()
    {
        if (! $this->is13) {
            return $this;
        }

        return new Isbn(Internal\Converter::convertIsbn13to10($this->isbn), false);
    }

    /**
     * Returns a copy of this Isbn, converted to ISBN-13.
     *
     * @return Isbn The ISBN-13.
     */
    public function to13()
    {
        if ($this->is13) {
            return $this;
        }

        return new Isbn(Internal\Converter::convertIsbn10to13($this->isbn), true);
    }

    /**
     * Returns whether this ISBN is in a recognized range.
     *
     * If this method returns false, we are unable to split the ISBN into parts, and format it with hyphens.
     * It would mean that either the ISBN number is wrong, or this version of the library is compiled against
     * an outdated data file from ISBN International.
     *
     * Note that this method returns true only means that the ISBN number is *potentially* valid,
     * but it is by no means a way to check if the ISBN number has been *assigned* yet.
     *
     * @return bool
     */
    public function isValidRange()
    {
        return $this->parts !== null;
    }

    /**
     * @return array
     *
     * @throws IsbnException If this ISBN is not in a recognized range.
     */
    public function getParts()
    {
        if ($this->parts === null) {
            throw IsbnException::unknownRange($this->isbn);
        }

        return $this->parts;
    }

    /**
     * Returns the GS1 (EAN) prefix of an ISBN-13.
     *
     * This prefix can be either 978 or 979.
     *
     * @return string
     *
     * @throws IsbnException If this ISBN is not in a recognized range, or if it's an ISBN-10.
     */
    public function getPrefix()
    {
        if (! $this->is13) {
            throw new IsbnException('Cannot get the GS1 prefix of an ISBN-10.');
        }

        if ($this->parts === null) {
            throw IsbnException::unknownRange($this->isbn);
        }

        return $this->parts[0];
    }

    /**
     * Returns the group identifier.
     *
     * The group or country identifier identifies a national or geographic grouping of publishers.
     *
     * @return string
     *
     * @throws IsbnException If this ISBN is not in a recognized range.
     */
    public function getGroupIdentifier()
    {
        if ($this->parts === null) {
            throw IsbnException::unknownRange($this->isbn);
        }

        return $this->parts[$this->is13 ? 1 : 0];
    }

    /**
     * Returns the publisher identifier.
     *
     * The publisher identifier identifies a particular publisher within a group.
     *
     * @return string
     *
     * @throws IsbnException If this ISBN is not in a recognized range.
     */
    public function getPublisherIdentifier()
    {
        if ($this->parts === null) {
            throw IsbnException::unknownRange($this->isbn);
        }

        return $this->parts[$this->is13 ? 2 : 1];
    }

    /**
     * Returns the title identifier.
     *
     * The title identifier identifies a particular title or edition of a title.
     *
     * @return string
     *
     * @throws IsbnException If this ISBN is not in a recognized range.
     */
    public function getTitleIdentifier()
    {
        if ($this->parts === null) {
            throw IsbnException::unknownRange($this->isbn);
        }

        return $this->parts[$this->is13 ? 3 : 2];
    }

    /**
     * Returns the check digit.
     *
     * The check digit is the single digit at the end of the ISBN which validates the ISBN.
     *
     * @return string
     *
     * @throws IsbnException If this ISBN is not in a recognized range.
     */
    public function getCheckDigit()
    {
        if ($this->parts === null) {
            throw IsbnException::unknownRange($this->isbn);
        }

        return $this->parts[$this->is13 ? 4 : 3];
    }

    /**
     * Returns the formatted (hyphenated) ISBN number.
     *
     * If the ISBN number is not in a recognized range, it is returned unformatted.
     *
     * @return string
     */
    public function format()
    {
        if ($this->parts === null) {
            return $this->isbn;
        }

        return implode('-', $this->parts);
    }

    /**
     * Returns the unformatted ISBN number.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->isbn;
    }
}
