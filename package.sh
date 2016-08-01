#!/bin/sh
mkdir yamarket
cp * yamarket
cp -r css yamarket/
cp -r js yamarket/
cp -r translations yamarket/
rm yamarket.zip
zip -r yamarket.zip yamarket  -x \*.git\* \*.DS_Store\* \*.idea\* \*.zip
rm -rf yamarket
