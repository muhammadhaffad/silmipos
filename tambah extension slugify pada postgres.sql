CREATE EXTENSION IF NOT EXISTS "unaccent";

CREATE OR REPLACE FUNCTION slugify("value" TEXT)
RETURNS TEXT AS $$
BEGIN
  RETURN regexp_replace(
           regexp_replace(
             lower(unaccent("value")), -- Lowercase and remove accents in one step
             '[^a-z0-9\\-_]+', '-', 'gi' -- Replace non-alphanumeric characters with hyphens
           ),
           '(^-+|-+$)', '', 'g' -- Remove leading and trailing hyphens
         );
END
$$ LANGUAGE plpgsql STRICT IMMUTABLE;
