<xsl:stylesheet
    version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    >

    <xsl:output
        method="html"
        indent="yes"
        />

    <xsl:template match="/plugins">

        <ul>

        <xsl:for-each select="plugin">

            <li>
                <xsl:element name="a">
                    <xsl:attribute name="href">
                        <xsl:value-of select="pluginuri" />
                    </xsl:attribute>
                    <xsl:value-of select="title" disable-output-escaping="yes" />
                </xsl:element>
                <xsl:text>, </xsl:text>
                <small>
                    <xsl:value-of select="$version" />
                    <xsl:text> </xsl:text>
                    <xsl:value-of select="version" />
                    <xsl:text> </xsl:text>
                    <cite>
                        <xsl:element name="a">
                            <xsl:attribute name="href">
                                <xsl:value-of select="authoruri" />
                            </xsl:attribute>
                            <xsl:value-of select="byline" disable-output-escaping="yes" />
                        </xsl:element>
                    </cite>
                </small>
            </li>

        </xsl:for-each>

        </ul>

    </xsl:template>

</xsl:stylesheet>