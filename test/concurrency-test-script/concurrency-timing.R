
# data collection via...
# (n=0; export postgres_bloat_rows=${n} postgres_bloat_vacuum=1; echo "next.time" > /tmp/next.log; sh concurrency-test.sh; wc -l /tmp/next.log /tmp/jqjobs-concurrency.log; mv /tmp/next.log /tmp/next-${postgres_bloat_rows}-bloat-vacuum-${postgres_bloat_vacuum}.log)
reports <- c(
"/tmp/next-0-bloat-vacuum-0.log", 
"/tmp/next-1000000-bloat-vacuum-0.log", 
"/tmp/next-2000000-bloat-vacuum-0.log", 
"/tmp/next-2500000-bloat-vacuum-0.log", 
"/tmp/next-3000000-bloat-vacuum-0.log", 
"/tmp/next-3500000-bloat-vacuum-0.log", 
"/tmp/next-4000000-bloat-vacuum-0.log", 
"/tmp/next-4000000-bloat-vacuum-1.log"
)

par(mfcol=c(ceiling(length(reports)/2),2))

for (n in reports) {
	dataFile =  as.character(n)
	data <- read.csv(dataFile)
	plot(density(data$next.time, from=0,to=10), main=dataFile)
}