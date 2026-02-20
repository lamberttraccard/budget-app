<?php

namespace App\Parsers;

use App\Exceptions\UnrecognizedCsvFormatException;

class CsvParserFactory
{
    /** @param array<class-string<CsvParserInterface>> $parserClasses */
    public function __construct(
        private array $parserClasses = [],
    ) {}

    public function detect(string $content, string $filename): CsvParserInterface
    {
        foreach ($this->parserClasses as $parserClass) {
            $parser = app($parserClass);

            if ($parser->canParse($content, $filename)) {
                return $parser;
            }
        }

        $expected = array_map(
            fn (string $class) => trim(preg_replace('/([a-z])([A-Z])/', '$1 $2', str_replace('Parser', '', class_basename($class)))),
            $this->parserClasses,
        );

        throw new UnrecognizedCsvFormatException(
            'Unrecognized CSV format. Expected: '.implode(' or ', $expected).' export.',
        );
    }
}
