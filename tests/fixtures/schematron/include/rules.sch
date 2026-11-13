<?xml version="1.0" encoding="UTF-8"?>
<pattern xmlns="http://purl.oclc.org/dsdl/schematron" id="rules">
  <rule context="cbc:ID[contains($CODES, concat(' ', ., ' '))]">
    <assert id="R-1" flag="fatal" test="string-length(.) le u:limit()">The ID is too long.</assert>
  </rule>
</pattern>
