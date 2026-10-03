-- Fill CheckM completeness/contamination on the MAGs listing.
-- Source: acropora_kenti_checkm_values.tsv
-- Values computed with CheckM2 1.1.0 (DIAMOND db uniref100.KO.1.dmnd).
--
-- One UPDATE per MAG so the diff is reviewable and a re-run is a no-op.
--
-- ALL 115 rows are written, including the 14 that already carried NCBI
-- CheckM1 values and GCA_012269805.1 (completeness only).  That is
-- deliberate: the column becomes a single method end to end.  The
-- previous values are preserved in the TSV's previous_ncbi_* columns
-- if a rollback is ever needed.
--
START TRANSACTION;

UPDATE MAGs SET CheckMcompleteness = 84.18, CheckMcontamination = 2.29 WHERE AssemblyAccession = 'GCA_012267325.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 96.21, CheckMcontamination = 0.71 WHERE AssemblyAccession = 'GCA_012267385.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 81.03, CheckMcontamination = 7.06 WHERE AssemblyAccession = 'GCA_012267405.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 97.34, CheckMcontamination = 5.37 WHERE AssemblyAccession = 'GCA_012267415.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 88.42, CheckMcontamination = 7.17 WHERE AssemblyAccession = 'GCA_012267425.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 67.47, CheckMcontamination = 3.33 WHERE AssemblyAccession = 'GCA_012267435.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 82.35, CheckMcontamination = 11.6 WHERE AssemblyAccession = 'GCA_012267485.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 92.2, CheckMcontamination = 2.45 WHERE AssemblyAccession = 'GCA_012267505.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 86.19, CheckMcontamination = 8.96 WHERE AssemblyAccession = 'GCA_012267525.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 93.09, CheckMcontamination = 21.35 WHERE AssemblyAccession = 'GCA_012267535.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 92.85, CheckMcontamination = 2.32 WHERE AssemblyAccession = 'GCA_012267545.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 90.26, CheckMcontamination = 12.51 WHERE AssemblyAccession = 'GCA_012267575.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 93.88, CheckMcontamination = 1.69 WHERE AssemblyAccession = 'GCA_012267605.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 80.97, CheckMcontamination = 4.37 WHERE AssemblyAccession = 'GCA_012269365.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 78.39, CheckMcontamination = 1.97 WHERE AssemblyAccession = 'GCA_012269375.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 90.91, CheckMcontamination = 1.34 WHERE AssemblyAccession = 'GCA_012269405.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 90.8, CheckMcontamination = 1.12 WHERE AssemblyAccession = 'GCA_012269445.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 78.72, CheckMcontamination = 1.16 WHERE AssemblyAccession = 'GCA_012269465.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 82.65, CheckMcontamination = 3.15 WHERE AssemblyAccession = 'GCA_012269475.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 79.28, CheckMcontamination = 5.35 WHERE AssemblyAccession = 'GCA_012269485.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 77.07, CheckMcontamination = 2.36 WHERE AssemblyAccession = 'GCA_012269495.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 99.13, CheckMcontamination = 0.21 WHERE AssemblyAccession = 'GCA_012269545.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 95.51, CheckMcontamination = 6.78 WHERE AssemblyAccession = 'GCA_012269555.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 93.26, CheckMcontamination = 5.22 WHERE AssemblyAccession = 'GCA_012269585.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 96.33, CheckMcontamination = 0.96 WHERE AssemblyAccession = 'GCA_012269595.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 75.15, CheckMcontamination = 11.02 WHERE AssemblyAccession = 'GCA_012269625.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 88.61, CheckMcontamination = 4.98 WHERE AssemblyAccession = 'GCA_012269645.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 76.54, CheckMcontamination = 3.44 WHERE AssemblyAccession = 'GCA_012269655.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 73.38, CheckMcontamination = 1.6 WHERE AssemblyAccession = 'GCA_012269685.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 73.61, CheckMcontamination = 1.13 WHERE AssemblyAccession = 'GCA_012269695.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 81.65, CheckMcontamination = 3.56 WHERE AssemblyAccession = 'GCA_012269725.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 100.0, CheckMcontamination = 2.37 WHERE AssemblyAccession = 'GCA_012269745.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 80.85, CheckMcontamination = 3.19 WHERE AssemblyAccession = 'GCA_012269755.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 84.98, CheckMcontamination = 4.61 WHERE AssemblyAccession = 'GCA_012269785.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 81.57, CheckMcontamination = 0.18 WHERE AssemblyAccession = 'GCA_012269805.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 99.91, CheckMcontamination = 2.67 WHERE AssemblyAccession = 'GCA_012269825.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 85.54, CheckMcontamination = 3.43 WHERE AssemblyAccession = 'GCA_012269835.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 82.11, CheckMcontamination = 4.97 WHERE AssemblyAccession = 'GCA_012269845.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 92.77, CheckMcontamination = 0.2 WHERE AssemblyAccession = 'GCA_012269885.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 77.7, CheckMcontamination = 2.83 WHERE AssemblyAccession = 'GCA_012269905.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 80.3, CheckMcontamination = 0.27 WHERE AssemblyAccession = 'GCA_012269915.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 90.9, CheckMcontamination = 0.2 WHERE AssemblyAccession = 'GCA_012269935.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 80.28, CheckMcontamination = 3.24 WHERE AssemblyAccession = 'GCA_012269955.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 93.89, CheckMcontamination = 0.77 WHERE AssemblyAccession = 'GCA_012269985.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 93.55, CheckMcontamination = 0.01 WHERE AssemblyAccession = 'GCA_012270005.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 99.42, CheckMcontamination = 0.44 WHERE AssemblyAccession = 'GCA_012270025.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 86.19, CheckMcontamination = 2.65 WHERE AssemblyAccession = 'GCA_012270035.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 100.0, CheckMcontamination = 1.04 WHERE AssemblyAccession = 'GCA_012270045.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 99.84, CheckMcontamination = 0.98 WHERE AssemblyAccession = 'GCA_012270065.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 90.31, CheckMcontamination = 2.18 WHERE AssemblyAccession = 'GCA_012270105.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 96.86, CheckMcontamination = 0.13 WHERE AssemblyAccession = 'GCA_012270115.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 95.92, CheckMcontamination = 0.08 WHERE AssemblyAccession = 'GCA_012270125.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 76.79, CheckMcontamination = 0.55 WHERE AssemblyAccession = 'GCA_012270135.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 87.94, CheckMcontamination = 0.0 WHERE AssemblyAccession = 'GCA_012270185.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 92.8, CheckMcontamination = 1.78 WHERE AssemblyAccession = 'GCA_012270205.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 95.61, CheckMcontamination = 7.18 WHERE AssemblyAccession = 'GCA_012270225.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 96.89, CheckMcontamination = 0.77 WHERE AssemblyAccession = 'GCA_012270245.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 77.91, CheckMcontamination = 6.11 WHERE AssemblyAccession = 'GCA_012270255.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 67.84, CheckMcontamination = 1.79 WHERE AssemblyAccession = 'GCA_012270265.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 93.52, CheckMcontamination = 0.7 WHERE AssemblyAccession = 'GCA_012270305.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 80.03, CheckMcontamination = 0.37 WHERE AssemblyAccession = 'GCA_012270315.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 91.4, CheckMcontamination = 1.49 WHERE AssemblyAccession = 'GCA_012270345.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 83.44, CheckMcontamination = 1.86 WHERE AssemblyAccession = 'GCA_012270355.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 90.86, CheckMcontamination = 1.53 WHERE AssemblyAccession = 'GCA_012270375.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 90.14, CheckMcontamination = 2.55 WHERE AssemblyAccession = 'GCA_012270385.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 95.7, CheckMcontamination = 0.0 WHERE AssemblyAccession = 'GCA_012270425.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 99.84, CheckMcontamination = 0.03 WHERE AssemblyAccession = 'GCA_012270435.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 83.06, CheckMcontamination = 0.96 WHERE AssemblyAccession = 'GCA_012270465.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 89.65, CheckMcontamination = 4.98 WHERE AssemblyAccession = 'GCA_012270475.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 99.52, CheckMcontamination = 0.52 WHERE AssemblyAccession = 'GCA_012270485.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 69.67, CheckMcontamination = 5.02 WHERE AssemblyAccession = 'GCA_012270525.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 91.35, CheckMcontamination = 5.47 WHERE AssemblyAccession = 'GCA_012270545.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 97.84, CheckMcontamination = 1.16 WHERE AssemblyAccession = 'GCA_012270555.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 100.0, CheckMcontamination = 1.56 WHERE AssemblyAccession = 'GCA_012270585.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 88.5, CheckMcontamination = 0.31 WHERE AssemblyAccession = 'GCA_012270595.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 77.74, CheckMcontamination = 6.86 WHERE AssemblyAccession = 'GCA_012270625.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 88.72, CheckMcontamination = 9.33 WHERE AssemblyAccession = 'GCA_012270635.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 77.66, CheckMcontamination = 7.32 WHERE AssemblyAccession = 'GCA_012270665.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 94.29, CheckMcontamination = 7.63 WHERE AssemblyAccession = 'GCA_012270675.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 88.95, CheckMcontamination = 5.61 WHERE AssemblyAccession = 'GCA_012270695.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 76.09, CheckMcontamination = 7.96 WHERE AssemblyAccession = 'GCA_012270725.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 88.76, CheckMcontamination = 5.2 WHERE AssemblyAccession = 'GCA_012270735.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 63.25, CheckMcontamination = 5.87 WHERE AssemblyAccession = 'GCA_012270765.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 93.02, CheckMcontamination = 7.72 WHERE AssemblyAccession = 'GCA_012270775.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 95.06, CheckMcontamination = 2.28 WHERE AssemblyAccession = 'GCA_012270795.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 88.7, CheckMcontamination = 5.8 WHERE AssemblyAccession = 'GCA_012270805.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 68.03, CheckMcontamination = 6.24 WHERE AssemblyAccession = 'GCA_012270825.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 92.64, CheckMcontamination = 8.64 WHERE AssemblyAccession = 'GCA_012270865.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 81.87, CheckMcontamination = 5.47 WHERE AssemblyAccession = 'GCA_012270875.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 93.81, CheckMcontamination = 2.9 WHERE AssemblyAccession = 'GCA_012270885.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 93.27, CheckMcontamination = 10.03 WHERE AssemblyAccession = 'GCA_012270915.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 80.35, CheckMcontamination = 9.42 WHERE AssemblyAccession = 'GCA_012270935.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 94.65, CheckMcontamination = 0.02 WHERE AssemblyAccession = 'GCA_012270965.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 98.07, CheckMcontamination = 5.95 WHERE AssemblyAccession = 'GCA_012270975.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 96.77, CheckMcontamination = 0.29 WHERE AssemblyAccession = 'GCA_012270995.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 85.22, CheckMcontamination = 3.14 WHERE AssemblyAccession = 'GCA_012271015.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 90.2, CheckMcontamination = 8.69 WHERE AssemblyAccession = 'GCA_012271025.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 92.16, CheckMcontamination = 10.6 WHERE AssemblyAccession = 'GCA_012271065.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 96.23, CheckMcontamination = 2.38 WHERE AssemblyAccession = 'GCA_012271085.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 78.94, CheckMcontamination = 2.89 WHERE AssemblyAccession = 'GCA_012271095.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 83.62, CheckMcontamination = 5.73 WHERE AssemblyAccession = 'GCA_012271125.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 96.04, CheckMcontamination = 3.57 WHERE AssemblyAccession = 'GCA_012271135.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 71.58, CheckMcontamination = 2.44 WHERE AssemblyAccession = 'GCA_012271165.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 95.6, CheckMcontamination = 0.8 WHERE AssemblyAccession = 'GCA_012271185.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 92.31, CheckMcontamination = 0.05 WHERE AssemblyAccession = 'GCA_012271205.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 77.09, CheckMcontamination = 4.09 WHERE AssemblyAccession = 'GCA_012271215.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 91.84, CheckMcontamination = 3.67 WHERE AssemblyAccession = 'GCA_012271225.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 87.23, CheckMcontamination = 3.04 WHERE AssemblyAccession = 'GCA_012271265.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 84.62, CheckMcontamination = 2.02 WHERE AssemblyAccession = 'GCA_012271275.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 90.9, CheckMcontamination = 0.2 WHERE AssemblyAccession = 'GCF_012269935.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 93.55, CheckMcontamination = 0.01 WHERE AssemblyAccession = 'GCF_012270005.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 99.42, CheckMcontamination = 0.44 WHERE AssemblyAccession = 'GCF_012270025.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 96.86, CheckMcontamination = 0.13 WHERE AssemblyAccession = 'GCF_012270115.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 90.86, CheckMcontamination = 1.53 WHERE AssemblyAccession = 'GCF_012270375.1' AND host = 'Acropora kenti';
UPDATE MAGs SET CheckMcompleteness = 99.84, CheckMcontamination = 0.03 WHERE AssemblyAccession = 'GCF_012270435.1' AND host = 'Acropora kenti';

COMMIT;

-- Verify: every row above should now be non-NULL and match the TSV.
SELECT AssemblyAccession, CheckMcompleteness, CheckMcontamination FROM MAGs WHERE AssemblyAccession IN ('GCA_012267325.1','GCA_012267385.1','GCA_012267405.1','GCA_012267415.1','GCA_012267425.1','GCA_012267435.1','GCA_012267485.1','GCA_012267505.1','GCA_012267525.1','GCA_012267535.1','GCA_012267545.1','GCA_012267575.1','GCA_012267605.1','GCA_012269365.1','GCA_012269375.1','GCA_012269405.1','GCA_012269445.1','GCA_012269465.1','GCA_012269475.1','GCA_012269485.1','GCA_012269495.1','GCA_012269545.1','GCA_012269555.1','GCA_012269585.1','GCA_012269595.1','GCA_012269625.1','GCA_012269645.1','GCA_012269655.1','GCA_012269685.1','GCA_012269695.1','GCA_012269725.1','GCA_012269745.1','GCA_012269755.1','GCA_012269785.1','GCA_012269805.1','GCA_012269825.1','GCA_012269835.1','GCA_012269845.1','GCA_012269885.1','GCA_012269905.1','GCA_012269915.1','GCA_012269935.1','GCA_012269955.1','GCA_012269985.1','GCA_012270005.1','GCA_012270025.1','GCA_012270035.1','GCA_012270045.1','GCA_012270065.1','GCA_012270105.1','GCA_012270115.1','GCA_012270125.1','GCA_012270135.1','GCA_012270185.1','GCA_012270205.1','GCA_012270225.1','GCA_012270245.1','GCA_012270255.1','GCA_012270265.1','GCA_012270305.1','GCA_012270315.1','GCA_012270345.1','GCA_012270355.1','GCA_012270375.1','GCA_012270385.1','GCA_012270425.1','GCA_012270435.1','GCA_012270465.1','GCA_012270475.1','GCA_012270485.1','GCA_012270525.1','GCA_012270545.1','GCA_012270555.1','GCA_012270585.1','GCA_012270595.1','GCA_012270625.1','GCA_012270635.1','GCA_012270665.1','GCA_012270675.1','GCA_012270695.1','GCA_012270725.1','GCA_012270735.1','GCA_012270765.1','GCA_012270775.1','GCA_012270795.1','GCA_012270805.1','GCA_012270825.1','GCA_012270865.1','GCA_012270875.1','GCA_012270885.1','GCA_012270915.1','GCA_012270935.1','GCA_012270965.1','GCA_012270975.1','GCA_012270995.1','GCA_012271015.1','GCA_012271025.1','GCA_012271065.1','GCA_012271085.1','GCA_012271095.1','GCA_012271125.1','GCA_012271135.1','GCA_012271165.1','GCA_012271185.1','GCA_012271205.1','GCA_012271215.1','GCA_012271225.1','GCA_012271265.1','GCA_012271275.1','GCF_012269935.1','GCF_012270005.1','GCF_012270025.1','GCF_012270115.1','GCF_012270375.1','GCF_012270435.1') AND host = 'Acropora kenti' ORDER BY AssemblyAccession;
