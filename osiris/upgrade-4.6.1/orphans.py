# Build one SQL query that counts orphaned rows for every foreign key the NEW
# Registry tree defines on tables/columns that already exist in the OLD tree.
# Rows listed must be fixed before upgradeVersion, or the schema step fails.
# Usage: python3 orphans.py NEW_TREE OLD_TREE | mysql ... DBNAME   (read-only)
import re,glob,sys,os
NEW,OLD=sys.argv[1],sys.argv[2]
EN=['CephProvisioner','LdapUserPosixGroupProvisioner','UnixCluster']
def files(root):
    return [root+'/app/Config/Schema/schema.xml']+sorted(glob.glob(root+'/app/Plugin/*/Config/Schema/schema.xml'))+[root+f'/app/AvailablePlugin/{p}/Config/Schema/schema.xml' for p in EN]
F=re.compile(r'<field name="(\w+)"([^>]*?)(/?)>')
def parse(path):
    refs,cols=[],set()
    if not os.path.exists(path): return refs,cols
    x=open(path).read()
    for t in re.finditer(r'<table name="(\w+)">(.*?)</table>',x,re.S):
        tn,b=t.groups(); cols.add((tn,None))
        for m in F.finditer(b):
            cols.add((tn,m.group(1)))
            if m.group(3): continue
            r=re.search(r'REFERENCES\s+cm_(\w+)\(id\)',b[m.end():b.find('</field>',m.end())])
            if r: refs.append((tn,m.group(1),r.group(1)))
    return refs,cols
old=set().union(*[parse(f)[1] for f in files(OLD)])
new=sorted(set(sum([parse(f)[0] for f in files(NEW)],[])))
q=[f"select '{t}.{c}' fk,count(*) n from cm_{t} x left join cm_{r} y on y.id=x.{c} where x.{c} is not null and y.id is null" for t,c,r in new if (t,c) in old and (r,None) in old]
print("select * from (\n"+"\nunion all\n".join(q)+"\n) z where n>0;")
