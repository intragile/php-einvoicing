<?xml version="1.0" encoding="UTF-8"?>
<schema xmlns="http://purl.oclc.org/dsdl/schematron" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" queryBinding="xslt2">
  <ns prefix="cbc" uri="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"/>
  <ns prefix="u" uri="utils"/>
  <let name="base" value="10"/>
  <xsl:function name="u:limit" as="xs:integer">
    <xsl:sequence select="$LIMIT"/>
  </xsl:function>
  <include href="common.sch"/>
  <include href="rules.sch"/>
</schema>
