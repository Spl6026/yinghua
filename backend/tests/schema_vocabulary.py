"""[E-34] Independent check of emitted terms/domain/range against official Schema.org JSON-LD.

This checks our deliberately small JSON-LD subset; it is not Google's Rich Results engine.
The vocabulary file is downloaded to ignored artifacts, never shipped as app dependency.
"""
import json,re
from datetime import datetime,date
from urllib.parse import urlsplit

def term(value):
    return value.replace('schema:','https://schema.org/',1).replace('http://schema.org/','https://schema.org/',1)

def ids(value):
    return [term(row['@id']) for row in (value if isinstance(value,list) else [value]) if isinstance(row,dict) and '@id' in row]

class SchemaVocabulary:
    def __init__(self,path):
        self.terms={term(row['@id']):row for row in json.loads(path.read_text(encoding='utf8'))['@graph'] if '@id' in row}

    def ancestors(self,type_name):
        pending=[term(type_name)];found=set()
        while pending:
            current=pending.pop()
            if current in found:continue
            assert current in self.terms,('unknown type',current)
            found.add(current);pending.extend(ids(self.terms[current].get('rdfs:subClassOf',[])))
        return found

    def validate(self,graph):
        assert graph.get('@context')=='https://schema.org'
        nodes=graph['@graph'];references={node['@id']:node for node in nodes}
        assert len(references)==len(nodes),'duplicate graph identity'
        def check(node):
            if set(node)=={'@id'}:
                assert node['@id'] in references,('unresolved reference',node['@id']);return self.ancestors('https://schema.org/'+references[node['@id']]['@type'])
            ancestors=self.ancestors('https://schema.org/'+node['@type'])
            for key,value in node.items():
                if key.startswith('@'):continue
                definition=self.terms.get('https://schema.org/'+key);assert definition is not None,('unknown property',key)
                domain=set(ids(definition.get('schema:domainIncludes',[])))
                assert not domain or ancestors&domain,('invalid domain',node['@type'],key)
                ranges=set(ids(definition.get('schema:rangeIncludes',[])))
                if isinstance(value,dict):
                    actual=check(value);assert not ranges or actual&ranges,('invalid object range',key)
                elif isinstance(value,str):
                    if 'https://schema.org/Text' in ranges:continue
                    if 'https://schema.org/URL' in ranges:
                        assert urlsplit(value).scheme in ['http','https'],('invalid URL',key);continue
                    if 'https://schema.org/DateTime' in ranges:
                        assert datetime.fromisoformat(value.replace('Z','+00:00')).tzinfo is not None,('date timezone',key);continue
                    if 'https://schema.org/Date' in ranges:date.fromisoformat(value);continue
                    assert not ranges,('invalid literal range',key,sorted(ranges))
                else:raise AssertionError(('unexpected emitted value',key))
            return ancestors
        for node in nodes:check(node)
