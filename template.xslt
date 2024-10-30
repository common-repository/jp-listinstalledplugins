<xsl:stylesheet
    version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    >

    <xsl:output
        method="html"
        indent="yes"
        />

    <xsl:template match="/plugins">

        <xsl:if test="$caption">
            <div><xsl:value-of select="$caption" /></div>
        </xsl:if>

        <dl id="list-installed-plugins">

        <xsl:for-each select="plugin">

            <dt>
                <xsl:element name="a">
                    <xsl:attribute name="href">
                        <xsl:value-of select="pluginuri" />
                    </xsl:attribute>
                    <xsl:value-of select="title" disable-output-escaping="yes" />
                </xsl:element>
            </dt>
            <dd><xsl:value-of select="description" disable-output-escaping="yes" /></dd>
            <dd>
                <xsl:value-of select="$version" />
                <xsl:text>: </xsl:text>
                <xsl:value-of select="version" />
                <xsl:text>, </xsl:text>
                <cite>
                    <xsl:element name="a">
                        <xsl:attribute name="href">
                            <xsl:value-of select="authoruri" />
                        </xsl:attribute>
                        <xsl:value-of select="byline" disable-output-escaping="yes" />
                    </xsl:element>
                </cite>
            </dd>

        </xsl:for-each>

        </dl>

    </xsl:template>

</xsl:stylesheet>